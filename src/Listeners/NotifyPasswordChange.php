<?php

namespace Goldnead\Accounts\Listeners;

use Goldnead\Accounts\Events\PasswordChanged;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Support\AccountMailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Statamic\Events\UserSaved;
use Statamic\Events\UserSaving;
use Statamic\Facades\YAML;
use Throwable;

/**
 * "Dein Passwort wurde geändert", to the account's address.
 *
 * Watched at the save rather than at one form: Statamic's front-end password
 * form (`/!/auth/password`) fires no event, the CP fires
 * `UserPasswordChanged`, a reset fires Laravel's `PasswordReset`, and a host's
 * own code fires nothing. All of them save the user. The saving event says
 * whether the stored hash is about to change; the saved event sends, so a save
 * that fails announces nothing.
 *
 * A new account is not told its password changed: there was none before.
 * Switched off with `accounts.password_change.notify`.
 */
class NotifyPasswordChange
{
    /** @var array<string, true> user id => the password changes with this save */
    protected static array $changing = [];

    public function __construct(
        protected AccountMailer $mailer,
        protected ActivityBridge $activity,
    ) {}

    public function handleSaving(UserSaving $event): void
    {
        $user = $event->user;
        $id = (string) $user->id();

        unset(self::$changing[$id]);

        if (! config('accounts.password_change.notify', true)) {
            return;
        }

        try {
            if ($this->passwordChanges($user)) {
                self::$changing[$id] = true;
            }
        } catch (Throwable $e) {
            // Deciding whether to send a notice must never stop a save.
            Log::warning('statamic-accounts: could not tell whether a password changed.', [
                'user_id' => $id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function handleSaved(UserSaved $event): void
    {
        $user = $event->user;
        $id = (string) $user->id();

        if (! isset(self::$changing[$id])) {
            return;
        }

        unset(self::$changing[$id]);

        $email = (string) $user->email();

        if ($email === '') {
            return;
        }

        try {
            $this->mailer->send('password_changed', $email, [
                'user' => ['name' => $user->name(), 'email' => $email],
                'changed_at' => now()->setTimezone($this->zone())->locale(app()->getLocale())->isoFormat('LLL'),
            ]);
        } catch (Throwable $e) {
            // The password is changed either way; a lost notice is said loudly.
            Log::error('statamic-accounts: the password was changed and the notice could not be sent.', [
                'user_id' => $id,
                'exception' => $e->getMessage(),
            ]);
        }

        PasswordChanged::dispatch($id, $email, $user->name());

        $this->activity->record('accounts.password_changed', ['user_id' => $id], $user);
    }

    /**
     * Whether the save about to happen writes a different password hash than
     * the one stored. Eloquent users: the model's dirty state. File users: the
     * hash in the YAML on disk, because the Stache may hand out the very object
     * being changed, whose "original" is then no longer the stored one.
     */
    protected function passwordChanges(object $user): bool
    {
        if (method_exists($user, 'model') && ($model = $user->model()) instanceof Model) {
            return $model->exists && $model->getOriginal('password') !== null && $model->isDirty('password');
        }

        $new = method_exists($user, 'passwordHash') ? $user->passwordHash() : null;

        if (! is_string($new) || $new === '' || ! method_exists($user, 'path')) {
            return false;
        }

        $path = (string) $user->path();

        if (! is_file($path)) {
            return false;
        }

        $stored = YAML::file($path)->parse()['password_hash'] ?? null;

        return is_string($stored) && $stored !== '' && ! hash_equals($stored, $new);
    }

    protected function zone(): string
    {
        $zone = config('statamic.system.display_timezone') ?: config('app.timezone', 'UTC');

        return is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
    }
}
