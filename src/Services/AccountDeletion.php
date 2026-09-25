<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\Events\AccountDeleted;
use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Events\AccountDeletionBlocked;
use Goldnead\Accounts\Events\AccountDeletionCancelled;
use Goldnead\Accounts\Events\AccountDeletionRequested;
use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Support\AccountMailer;
use Goldnead\Accounts\Support\Users;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Statamic\Auth\User;
use Throwable;

/**
 * Deleting an account, with a period in which the customer can change their
 * mind.
 *
 * A request writes a pending row with the due date and mails a link to
 * withdraw it. The account keeps working. {@see purgeDue()}, run daily by
 * `accounts:purge`, handles what is due:
 *
 * - Something stands in the way (a running subscription, a team the person
 *   holds alone with other members): the request becomes `blocked`, the
 *   person gets one mail with the reasons and a withdraw link, and the next
 *   runs try again. Nothing is changed.
 * - Otherwise, with the `cancel` policy, running subscriptions are cancelled
 *   through payments first. That talks to the provider and is **not** part
 *   of the transaction below: a later rollback cannot undo it. So it is put
 *   on record (request meta, ledger entry `accounts.subscriptions_cancelled`)
 *   and said again if the request is withdrawn afterwards.
 * - Then, in one transaction: every eraser runs, {@see AccountDeleting} is
 *   dispatched (the user still exists), the user is deleted and checked to
 *   be gone (a `UserDeleting` veto or a file that could not be removed
 *   throws). A failure anywhere rolls the database back and the account
 *   stays scheduled. {@see AccountDeleted} and the last mail follow after
 *   the commit.
 */
class AccountDeletion
{
    /**
     * How long the withdraw link in the blocked-mail works.
     */
    public const BLOCKED_LINK_DAYS = 30;

    public function __construct(
        protected AccountMailer $mailer,
        protected ActivityBridge $activity,
        protected PersonalDataErasure $erasure,
        protected Impersonation $impersonation,
    ) {}

    /**
     * At least one day: a request must leave time to open the withdraw link.
     */
    public function graceDays(): int
    {
        return max(1, (int) config('accounts.deletion.grace_days', 14));
    }

    /**
     * What stands in the way of deleting this account now, as sentences.
     * `$audience`: `customer` ("you") or `admin` (third person). Changes
     * nothing. See {@see PersonalDataErasure::blockers()}.
     *
     * @return list<string>
     */
    public function blockers(User $user, string $audience = 'customer'): array
    {
        return $this->erasure->blockers($user, $audience);
    }

    /**
     * The open deletion request (pending or blocked), if there is one.
     */
    public function pending(User $user): ?AccountRequest
    {
        return AccountRequest::query()
            ->forUser((string) $user->id())
            ->ofType(AccountRequest::TYPE_DELETION)
            ->open()
            ->latest('id')
            ->first();
    }

    /**
     * Schedule the deletion. A second request while one is open returns the
     * first unchanged: asking twice does not move the date.
     *
     * @throws AccountException while impersonating, or when something blocks
     *                          the deletion (message lists what)
     */
    public function request(User $user, mixed $by = null): AccountRequest
    {
        $this->impersonation->refuseWhileActive();

        if ($existing = $this->pending($user)) {
            return $existing;
        }

        if ($blockers = $this->blockers($user)) {
            throw new AccountException(implode(' ', $blockers), 'account');
        }

        // No name, no meta: the row outlives the account.
        $request = AccountRequest::create([
            'user_id' => (string) $user->id(),
            'type' => AccountRequest::TYPE_DELETION,
            'email' => (string) $user->email(),
            'status' => AccountRequest::STATUS_PENDING,
            'due_at' => now()->addDays($this->graceDays()),
        ]);

        $due = $request->due_at ?? now();

        $this->mailer->send('deletion_scheduled', (string) $user->email(), [
            'user' => ['name' => $user->name(), 'email' => $user->email()],
            'scheduled_for' => $due->isoFormat('LL'),
            'grace_days' => $this->graceDays(),
            'action_url' => $this->cancelUrl($request),
        ]);

        AccountDeletionRequested::dispatch((string) $user->id(), (string) $user->email(), $user->name(), $due->toIso8601String());

        $this->activity->record('accounts.deletion_requested', [
            'user_id' => (string) $user->id(),
            'scheduled_for' => $due->toIso8601String(),
        ], $by ?? $user, 'accounts:deletion_requested:'.$request->id);

        return $request;
    }

    /**
     * A link to withdraw the request that works without signing in. Valid
     * until the deletion is due (or until `$until`), and useless once the
     * request is no longer open.
     */
    public function cancelUrl(AccountRequest $request, ?Carbon $until = null): string
    {
        return URL::temporarySignedRoute(
            'statamic.accounts.deletion.cancel',
            $until ?? $request->due_at ?? now()->addDay(),
            ['request' => $request->id],
        );
    }

    /**
     * Withdraw the open request. Refused while an admin is signed in as the
     * customer; the admin's own route in the Control Panel is not an
     * impersonation and passes.
     *
     * @throws AccountException while impersonating
     */
    public function cancel(User $user, mixed $by = null): bool
    {
        $this->impersonation->refuseWhileActive();

        $request = $this->pending($user);

        return $request !== null && $this->cancelRequest($request, $by ?? $user);
    }

    /**
     * Withdraw by request, for the signed link. Works for a pending request,
     * also one already due, and for a blocked one. False when it is no longer
     * open.
     */
    public function cancelRequest(AccountRequest $request, mixed $by = null): bool
    {
        if (! $request->isOpen() || $request->type !== AccountRequest::TYPE_DELETION) {
            return false;
        }

        $request->resolve(AccountRequest::STATUS_CANCELLED);

        $user = Users::find($request->user_id);

        AccountDeletionCancelled::dispatch($request->user_id, (string) ($user?->email() ?? $request->email), $user?->name());

        $this->activity->record('accounts.deletion_cancelled', [
            'user_id' => $request->user_id,
        ], $by, 'accounts:deletion_cancelled:'.$request->id);

        return true;
    }

    /**
     * Handle every open request whose grace period is over.
     *
     * One account failing does not stop the others; its request stays open
     * and the next run tries again.
     *
     * @return int the number of accounts deleted
     */
    public function purgeDue(): int
    {
        $deleted = 0;

        AccountRequest::query()
            ->ofType(AccountRequest::TYPE_DELETION)
            ->open()
            ->where('due_at', '<=', now())
            ->orderBy('id')
            ->each(function (AccountRequest $request) use (&$deleted) {
                try {
                    if ($this->deleteFor($request)) {
                        $deleted++;
                    }
                } catch (Throwable $e) {
                    Log::error('statamic-accounts: deleting an account failed; it stays scheduled.', [
                        'request' => $request->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            });

        return $deleted;
    }

    protected function deleteFor(AccountRequest $request): bool
    {
        $user = Users::find($request->user_id);

        if ($user === null) {
            // Deleted some other way in the meantime (the Control Panel, an
            // import). The erasers need the user and cannot run: what the
            // siblings hold stays, and somebody has to know.
            Log::warning('statamic-accounts: the account of a due deletion was already gone; the addons\' data about it was not erased by this deletion.', [
                'request' => $request->id,
            ]);

            $request->forceFill(['status' => AccountRequest::STATUS_COMPLETED, 'resolved_at' => now(), 'email' => null, 'meta' => ['outcome' => 'user_missing']])->save();

            $this->activity->record('accounts.deletion_user_missing', ['request_id' => $request->id], null, 'accounts:deletion_user_missing:'.$request->id);

            return false;
        }

        // Something may have come up during the grace period: a new
        // subscription, members joining a team the person holds alone.
        if ($blockers = $this->blockers($user)) {
            $this->block($request, $user, $blockers);

            return false;
        }

        $id = (string) $user->id();
        $email = (string) $user->email();
        $name = $user->name();

        // Only now, with nothing else in the way: the `cancel` policy's
        // cancellation. A subscription that cannot be cancelled blocks.
        $cancellation = $this->erasure->cancelSubscriptions($user);

        if ($cancellation['cancelled'] > 0) {
            $cancelled = (int) ($request->meta['subscriptions_cancelled'] ?? 0) + $cancellation['cancelled'];
            $request->forceFill(['meta' => array_merge($request->meta ?? [], ['subscriptions_cancelled' => $cancelled])])->save();

            $this->activity->record('accounts.subscriptions_cancelled', [
                'request_id' => $request->id,
                'user_id' => $id,
                'count' => $cancellation['cancelled'],
            ], null);
        }

        if ($cancellation['failed'] !== []) {
            $this->block($request, $user, $cancellation['failed']);

            return false;
        }

        $cancelledTotal = (int) ($request->meta['subscriptions_cancelled'] ?? 0);

        $results = DB::transaction(function () use ($user, $request, $id, $email, $name, $cancelledTotal) {
            $results = $this->erasure->erase($user);

            // After a successful erasure, while the user still exists. A
            // listener that throws rolls everything back.
            AccountDeleting::dispatch($id, $email, $name);

            // Inside the transaction: an Eloquent user goes with the rest or
            // not at all; a file user is deleted last, so a failure before
            // it leaves the file untouched.
            // Checked, not assumed: a `UserDeleting` listener can veto
            // (delete() returns false), and the file repository ignores a
            // failed unlink. Either way the account is still there, so
            // nothing may be marked done.
            if ($user->delete() === false || $this->stillExists($user)) {
                throw new RuntimeException('The user could not be deleted; the deletion is rolled back.');
            }

            $request->forceFill([
                'status' => AccountRequest::STATUS_COMPLETED,
                'resolved_at' => now(),
                // The request is all that is left: the id (pseudonymous, it
                // points at nothing now), the dates and what was deleted and
                // kept. Row counts, never a value from a row.
                'email' => null,
                'meta' => array_filter([
                    'erasure' => array_map(fn ($result) => $result->toArray(), $results),
                    'subscriptions_cancelled' => $cancelledTotal ?: null,
                ]),
            ])->save();

            return $results;
        });

        $this->mailer->send('account_deleted', $email, [
            'user' => ['name' => $name, 'email' => $email],
        ]);

        AccountDeleted::dispatch($id, $email, $name);

        $this->activity->record('accounts.deleted', [
            'request_id' => $request->id,
            'erased' => array_keys($results),
        ], null, 'accounts:deleted:request:'.$request->id);

        return true;
    }

    /**
     * Mark a due request blocked and tell the person why, once. A request
     * already blocked stays quiet; the reasons are asked for anew every run.
     *
     * @param  list<string>  $reasons
     */
    /**
     * Whether the user is still there after `delete()`: the file for a file
     * user, the row for an Eloquent one.
     */
    protected function stillExists(User $user): bool
    {
        if (method_exists($user, 'model')) {
            $model = $user->model();

            return $model !== null && $model->newQuery()->whereKey($model->getKey())->exists();
        }

        return method_exists($user, 'path') && is_string($path = $user->path()) && file_exists($path);
    }

    /**
     * How many subscriptions a (possibly withdrawn) request already had
     * cancelled through the `cancel` policy.
     */
    public function subscriptionsCancelled(AccountRequest $request): int
    {
        return (int) ($request->meta['subscriptions_cancelled'] ?? 0);
    }

    protected function block(AccountRequest $request, User $user, array $reasons): void
    {
        if ($request->isBlocked()) {
            return;
        }

        $list = '<ul>'.implode('', array_map(fn (string $reason) => '<li>'.e($reason).'</li>', $reasons)).'</ul>';

        // The mail first: `blocked` means "told". If it cannot be sent, the
        // exception leaves the request pending and the next run tries again.
        $this->mailer->send('deletion_blocked', (string) $user->email(), [
            'user' => ['name' => $user->name(), 'email' => $user->email()],
            'reasons_list' => $list,
            'action_url' => $this->cancelUrl($request, now()->addDays(self::BLOCKED_LINK_DAYS)),
            'link_days' => self::BLOCKED_LINK_DAYS,
        ]);

        $request->forceFill([
            'status' => AccountRequest::STATUS_BLOCKED,
            'meta' => array_merge($request->meta ?? [], ['blocked_at' => now()->toIso8601String(), 'reasons' => count($reasons)]),
        ])->save();

        AccountDeletionBlocked::dispatch((string) $user->id(), (string) $user->email(), $user->name(), count($reasons));

        $this->activity->record('accounts.deletion_blocked', [
            'user_id' => (string) $user->id(),
            'reasons' => count($reasons),
        ], null, 'accounts:deletion_blocked:'.$request->id);

        Log::warning('statamic-accounts: a due deletion is blocked; the account stays and the person was told.', [
            'request' => $request->id,
            'reasons' => count($reasons),
        ]);
    }
}
