<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\Events\EmailChanged;
use Goldnead\Accounts\Events\EmailChangeRequested;
use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Support\AccountMailer;
use Goldnead\Accounts\Support\Users;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Statamic\Auth\User;

/**
 * Moving an account to a new address.
 *
 * The new address is kept on a pending request and only written onto the
 * user once the link sent to it is opened. Until then the old address stays
 * the login and receives everything. A second request replaces the first.
 */
class EmailChange
{
    public function __construct(
        protected AccountMailer $mailer,
        protected ActivityBridge $activity,
        protected EmailVerification $verification,
        protected Impersonation $impersonation,
    ) {}

    /**
     * @throws AccountException when the address is invalid, unchanged or taken
     */
    public function request(User $user, string $newEmail): AccountRequest
    {
        $this->impersonation->refuseWhileActive();

        $newEmail = trim($newEmail);

        if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new AccountException(__('accounts::messages.email_invalid'), 'email');
        }

        if (mb_strtolower($newEmail) === mb_strtolower((string) $user->email())) {
            throw new AccountException(__('accounts::messages.email_unchanged'), 'email');
        }

        if ($this->taken($newEmail, $user)) {
            throw new AccountException(__('accounts::messages.email_taken'), 'email');
        }

        $this->pendingQuery($user)->each(fn (AccountRequest $old) => $old->resolve(AccountRequest::STATUS_CANCELLED));

        $expires = now()->addMinutes((int) config('accounts.email_change.expire_minutes', 1440));

        $request = AccountRequest::create([
            'user_id' => (string) $user->id(),
            'type' => AccountRequest::TYPE_EMAIL_CHANGE,
            'email' => $newEmail,
            'status' => AccountRequest::STATUS_PENDING,
            'due_at' => $expires,
        ]);

        $this->mailer->send('confirm_email_change', $newEmail, [
            'user' => ['name' => $user->name(), 'email' => $user->email()],
            'new_email' => $newEmail,
            'old_email' => $user->email(),
            'action_url' => $this->url($request),
            'expires_in_hours' => (int) round((int) config('accounts.email_change.expire_minutes', 1440) / 60),
        ]);

        EmailChangeRequested::dispatch((string) $user->id(), (string) $user->email(), $user->name(), $newEmail);

        return $request;
    }

    public function url(AccountRequest $request): string
    {
        return URL::temporarySignedRoute(
            'statamic.accounts.email.confirm',
            $request->due_at ?? now()->addDay(),
            ['request' => $request->id, 'hash' => $this->verification->hash((string) $request->email)],
        );
    }

    public function pending(User $user): ?AccountRequest
    {
        return $this->pendingQuery($user)->latest('id')->first();
    }

    /**
     * Apply a confirmed request. The signature is checked by the route; this
     * checks that the request is still open, the hash matches the address it
     * was sent to, and nobody took that address in the meantime.
     *
     * @throws AccountException
     */
    public function confirm(int $requestId, string $hash): User
    {
        $request = AccountRequest::query()->ofType(AccountRequest::TYPE_EMAIL_CHANGE)->find($requestId);

        if ($request === null || ! $request->isPending() || ! hash_equals($this->verification->hash((string) $request->email), $hash)) {
            throw new AccountException(__('accounts::messages.link_invalid'));
        }

        if ($request->due_at !== null && $request->due_at->isPast()) {
            $request->resolve(AccountRequest::STATUS_CANCELLED);

            throw new AccountException(__('accounts::messages.link_invalid'));
        }

        $user = Users::find($request->user_id);

        if ($user === null) {
            $request->resolve(AccountRequest::STATUS_CANCELLED);

            throw new AccountException(__('accounts::messages.link_invalid'));
        }

        $newEmail = (string) $request->email;

        if ($this->taken($newEmail, $user)) {
            throw new AccountException(__('accounts::messages.email_taken'), 'email');
        }

        $oldEmail = (string) $user->email();

        $user->email($newEmail);
        // Opening the link proves the new address, so it counts as confirmed.
        $user->set($this->verification->field(), now()->toDateTimeString());
        $user->save();

        $request->resolve(AccountRequest::STATUS_COMPLETED);

        if (config('accounts.email_change.notify_old_address', true)) {
            $this->mailer->send('email_changed', $oldEmail, [
                'user' => ['name' => $user->name(), 'email' => $newEmail],
                'new_email' => $newEmail,
                'old_email' => $oldEmail,
            ]);
        }

        EmailChanged::dispatch((string) $user->id(), $newEmail, $user->name(), $oldEmail);

        $this->activity->record('accounts.email_changed', [
            'user_id' => (string) $user->id(),
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ], $user, 'accounts:email_changed:'.$request->id);

        return $user;
    }

    public function cancel(User $user): bool
    {
        $cancelled = false;

        $this->pendingQuery($user)->each(function (AccountRequest $request) use (&$cancelled) {
            $request->resolve(AccountRequest::STATUS_CANCELLED);
            $cancelled = true;
        });

        return $cancelled;
    }

    protected function taken(string $email, User $user): bool
    {
        $other = Users::findByEmail($email);

        return $other !== null && (string) $other->id() !== (string) $user->id();
    }

    /**
     * @return Builder<AccountRequest>
     */
    protected function pendingQuery(User $user)
    {
        return AccountRequest::query()
            ->forUser((string) $user->id())
            ->ofType(AccountRequest::TYPE_EMAIL_CHANGE)
            ->pending();
    }
}
