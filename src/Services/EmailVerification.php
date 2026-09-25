<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\Events\EmailVerificationSent;
use Goldnead\Accounts\Events\EmailVerified;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Support\AccountMailer;
use Goldnead\Accounts\Support\Users;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Statamic\Auth\User;

/**
 * Confirming that an account's address belongs to the person using it.
 *
 * The link is a Laravel temporary signed URL carrying the user id and a hash
 * of the address it was sent to. A changed expiry, a changed id, or a link
 * for an address the account no longer has: all rejected.
 */
class EmailVerification
{
    public function __construct(
        protected AccountMailer $mailer,
        protected ActivityBridge $activity,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('accounts.verification.enabled', true);
    }

    public function field(): string
    {
        return (string) config('accounts.verification.field', 'email_verified_at');
    }

    public function isVerified(User $user): bool
    {
        return filled($user->get($this->field()));
    }

    public function verifiedAt(User $user): ?Carbon
    {
        $value = $user->get($this->field());

        if (blank($value)) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
    }

    /**
     * The signed link for the account's current address.
     */
    public function url(User $user): string
    {
        return URL::temporarySignedRoute(
            'statamic.accounts.verify',
            now()->addMinutes((int) config('accounts.verification.expire_minutes', 1440)),
            ['user' => (string) $user->id(), 'hash' => $this->hash((string) $user->email())],
        );
    }

    /**
     * Send the confirmation mail. Returns false, and sends nothing, when the
     * address is already confirmed.
     */
    public function send(User $user): bool
    {
        if ($this->isVerified($user)) {
            return false;
        }

        if ($model = $this->laravelModel($user)) {
            // Laravel's VerifyEmail, which email-templates renders from
            // `core-verify-email`. One mail, not this one on top.
            $model->sendEmailVerificationNotification();
        } else {
            $this->mailer->send('verify_email', (string) $user->email(), [
                'user' => ['name' => $user->name(), 'email' => $user->email()],
                'action_url' => $this->url($user),
                'expires_in_hours' => (int) round((int) config('accounts.verification.expire_minutes', 1440) / 60),
            ]);
        }

        EmailVerificationSent::dispatch((string) $user->id(), (string) $user->email(), $user->name());

        return true;
    }

    /**
     * Which mail confirms this user's address: `laravel` (the model's own
     * `MustVerifyEmail` notification) or `accounts` (this addon's mail).
     *
     * `accounts.verification.mail`: `auto` (default) takes Laravel's when the
     * user's Eloquent model implements `MustVerifyEmail` and the site has
     * Laravel's `verification.verify` route, which that mail links to.
     * Statamic's file users never implement it, so they always get this
     * addon's mail.
     */
    public function mailFor(User $user): string
    {
        return $this->laravelModel($user) !== null ? 'laravel' : 'accounts';
    }

    protected function laravelModel(User $user): ?MustVerifyEmail
    {
        $mode = (string) config('accounts.verification.mail', 'auto');

        if ($mode === 'accounts') {
            return null;
        }

        $model = method_exists($user, 'model') ? $user->model() : null;

        if (! $model instanceof MustVerifyEmail) {
            return null;
        }

        return $mode === 'laravel' || Route::has('verification.verify') ? $model : null;
    }

    /**
     * Laravel's own link was opened (`Illuminate\Auth\Events\Verified`): the
     * column is already set, what is missing is the account event and the
     * ledger entry.
     */
    public function recordVerified(User $user, mixed $by = null): void
    {
        EmailVerified::dispatch((string) $user->id(), (string) $user->email(), $user->name());

        $this->activity->record('accounts.email_verified', [
            'user_id' => (string) $user->id(),
        ], $by ?? $user, 'accounts:verified:'.$user->id().':'.sha1((string) $user->email()));
    }

    /**
     * Check a link's user and hash. The signature itself is checked by the
     * route's `signed` middleware before this runs; a caller outside a
     * request (an API endpoint in another addon) checks it there.
     */
    public function verify(string $userId, string $hash): ?User
    {
        $user = Users::find($userId);

        if ($user === null || ! hash_equals($this->hash((string) $user->email()), $hash)) {
            return null;
        }

        if (! $this->isVerified($user)) {
            $this->markVerified($user);
        }

        return $user;
    }

    public function markVerified(User $user, mixed $by = null): void
    {
        $user->set($this->field(), now()->toDateTimeString());
        $user->save();

        $this->recordVerified($user, $by);
    }

    public function hash(string $email): string
    {
        return sha1(mb_strtolower(trim($email)));
    }
}
