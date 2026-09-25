<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\Events\AccountDeleted;
use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Events\AccountDeletionCancelled;
use Goldnead\Accounts\Events\AccountDeletionRequested;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Support\AccountMailer;
use Goldnead\Accounts\Support\Users;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Statamic\Auth\User;
use Throwable;

/**
 * Deleting an account, with a period in which the customer can change their
 * mind.
 *
 * A request writes a pending row with the due date and mails a link to
 * withdraw it. The account keeps working. {@see purgeDue()}, run daily by
 * `accounts:purge`, deletes what is due: {@see AccountDeleting} first, while
 * the user still exists, then the user, then {@see AccountDeleted}.
 */
class AccountDeletion
{
    public function __construct(
        protected AccountMailer $mailer,
        protected ActivityBridge $activity,
    ) {}

    public function graceDays(): int
    {
        return max(0, (int) config('accounts.deletion.grace_days', 14));
    }

    public function pending(User $user): ?AccountRequest
    {
        return AccountRequest::query()
            ->forUser((string) $user->id())
            ->ofType(AccountRequest::TYPE_DELETION)
            ->pending()
            ->latest('id')
            ->first();
    }

    /**
     * Schedule the deletion. A second request while one is pending returns
     * the first unchanged: asking twice does not move the date.
     */
    public function request(User $user, mixed $by = null): AccountRequest
    {
        if ($existing = $this->pending($user)) {
            return $existing;
        }

        $request = AccountRequest::create([
            'user_id' => (string) $user->id(),
            'type' => AccountRequest::TYPE_DELETION,
            'email' => (string) $user->email(),
            'status' => AccountRequest::STATUS_PENDING,
            'due_at' => now()->addDays($this->graceDays()),
            'meta' => ['name' => $user->name()],
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
            'email' => (string) $user->email(),
            'scheduled_for' => $due->toIso8601String(),
        ], $by ?? $user, 'accounts:deletion_requested:'.$request->id);

        return $request;
    }

    /**
     * A link to withdraw the request that works without signing in. Valid
     * until the deletion is due, and useless after it was withdrawn.
     */
    public function cancelUrl(AccountRequest $request): string
    {
        return URL::temporarySignedRoute(
            'statamic.accounts.deletion.cancel',
            $request->due_at ?? now()->addDay(),
            ['request' => $request->id],
        );
    }

    public function cancel(User $user, mixed $by = null): bool
    {
        $request = $this->pending($user);

        return $request !== null && $this->cancelRequest($request, $by ?? $user);
    }

    /**
     * Withdraw by request id, for the signed link. False when the request is
     * no longer pending or already due.
     */
    public function cancelRequest(AccountRequest $request, mixed $by = null): bool
    {
        if (! $request->isPending() || $request->type !== AccountRequest::TYPE_DELETION) {
            return false;
        }

        if ($request->due_at !== null && $request->due_at->isPast()) {
            return false;
        }

        $request->resolve(AccountRequest::STATUS_CANCELLED);

        $name = $request->meta['name'] ?? null;

        AccountDeletionCancelled::dispatch($request->user_id, (string) $request->email, is_string($name) ? $name : null);

        $this->activity->record('accounts.deletion_cancelled', [
            'user_id' => $request->user_id,
            'email' => (string) $request->email,
        ], $by, 'accounts:deletion_cancelled:'.$request->id);

        return true;
    }

    /**
     * Delete every account whose grace period is over.
     *
     * One account failing (a listener throws) does not stop the others; its
     * request stays pending and the next run tries again.
     *
     * @return int the number of accounts deleted
     */
    public function purgeDue(): int
    {
        $deleted = 0;

        AccountRequest::query()
            ->ofType(AccountRequest::TYPE_DELETION)
            ->pending()
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
                        'user_id' => $request->user_id,
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
            // Deleted some other way in the meantime. Nothing left to do.
            $request->resolve(AccountRequest::STATUS_COMPLETED);

            return false;
        }

        $id = (string) $user->id();
        $email = (string) $user->email();
        $name = $user->name();

        AccountDeleting::dispatch($id, $email, $name);

        $user->delete();

        $request->forceFill([
            'status' => AccountRequest::STATUS_COMPLETED,
            'resolved_at' => now(),
            // The request is all that is left; it keeps neither name nor
            // address, only the id and the date.
            'email' => null,
            'meta' => null,
        ])->save();

        $this->mailer->send('account_deleted', $email, [
            'user' => ['name' => $name, 'email' => $email],
        ]);

        AccountDeleted::dispatch($id, $email, $name);

        $this->activity->record('accounts.deleted', [
            'user_id' => $id,
        ], null, 'accounts:deleted:'.$id);

        return true;
    }
}
