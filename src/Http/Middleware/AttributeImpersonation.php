<?php

namespace Goldnead\Accounts\Http\Middleware;

use Closure;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Support\Users as User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin is signed in as a customer, pin the identity through
 * goldnead/statamic-identity-contracts: the customer as actor, the admin in
 * `meta.impersonated_by`. Everything that records an actor (activity,
 * notifications, leadhub) then shows who really acted.
 *
 * Pushed onto the `web` group. A no-op without the sibling or outside an
 * impersonation.
 */
class AttributeImpersonation
{
    public const FACADE = '\Goldnead\IdentityContracts\Facades\IdentityContext';

    public function __construct(protected Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! class_exists(self::FACADE) || ! $request->hasSession() || ! $this->impersonation->active()) {
            return $next($request);
        }

        $user = User::current();

        if ($user === null) {
            return $next($request);
        }

        $facade = self::FACADE;
        $identity = $facade::resolve($user)->withMeta(['impersonated_by' => $this->impersonation->impersonatorId()]);

        $facade::setCurrent($identity);

        try {
            return $next($request);
        } finally {
            $facade::setCurrent(null);
        }
    }
}
