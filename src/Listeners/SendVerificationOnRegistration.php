<?php

namespace Goldnead\Accounts\Listeners;

use Goldnead\Accounts\Services\EmailVerification;
use Illuminate\Support\Facades\Log;
use Statamic\Events\UserRegistered;
use Throwable;

/**
 * Sends the confirmation mail after Statamic's `{{ user:register_form }}`.
 *
 * A site that registers users some other way (an API, an import) calls
 * `Accounts::verification()->send($user)` itself.
 */
class SendVerificationOnRegistration
{
    public function __construct(protected EmailVerification $verification) {}

    public function handle(UserRegistered $event): void
    {
        if (! $this->verification->enabled() || ! config('accounts.verification.send_on_register', true)) {
            return;
        }

        try {
            $this->verification->send($event->user);
        } catch (Throwable $e) {
            // The account exists; a failed mail must not turn the
            // registration into an error page. The notice has a resend
            // button.
            Log::error('statamic-accounts: the confirmation mail after registration could not be sent.', [
                'user_id' => (string) $event->user->id(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
