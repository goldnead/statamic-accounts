<?php

namespace Goldnead\Accounts\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Statamic\Actions\Impersonate;
use Statamic\Contracts\Auth\User;

/**
 * Signing in as a customer from the Control Panel.
 *
 * Statamic already does this (`Statamic\Actions\Impersonate`, the
 * `impersonate users` permission, `statamic.users.impersonate`). This class
 * runs core's action instead of copying it, so session keys, the elevated
 * session and "Stop impersonating" stay core's. What this addon adds is the
 * record (a listener on core's `ImpersonationStarted`/`Ended` writes to
 * activity) and the attribution (middleware pins the identity with the admin
 * named in its meta while the session lasts).
 */
class Impersonation
{
    public const SESSION_KEY = 'statamic_impersonated_by';

    public function enabled(): bool
    {
        return (bool) config('statamic.users.impersonate.enabled', true);
    }

    public function allowed(User $admin, User $target): bool
    {
        return $this->enabled()
            && ! $this->active()
            && (string) $admin->id() !== (string) $target->id()
            && $admin->can('impersonate', $target);
    }

    /**
     * @return string where to send the browser next
     *
     * @throws AuthorizationException
     */
    public function start(User $admin, User $target): string
    {
        if (! $this->allowed($admin, $target)) {
            throw new AuthorizationException(__('accounts::messages.impersonate_denied'));
        }

        $action = new Impersonate;
        $action->run(collect([$target]), []);

        if ($url = config('statamic.users.impersonate.redirect')) {
            return (string) $url;
        }

        return $target->can('access cp') ? cp_route('index') : (string) config('accounts.impersonation.redirect', '/');
    }

    public function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public function impersonatorId(): ?string
    {
        $id = session()->get(self::SESSION_KEY);

        return $id === null ? null : (string) $id;
    }
}
