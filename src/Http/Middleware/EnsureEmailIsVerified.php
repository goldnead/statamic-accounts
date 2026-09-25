<?php

namespace Goldnead\Accounts\Http\Middleware;

use Closure;
use Goldnead\Accounts\Services\EmailVerification;
use Illuminate\Http\Request;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `accounts.verified`: a signed-in user with an unconfirmed
 * address goes to `accounts.verification.notice_url`, a JSON caller gets 403.
 *
 * Guests pass. Whether a page needs a sign-in at all is the `auth`
 * middleware's question; putting both on a route is the intended use.
 */
class EnsureEmailIsVerified
{
    public function __construct(protected EmailVerification $verification) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = User::current();

        if ($user === null || ! $this->verification->enabled() || $this->verification->isVerified($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __('accounts::messages.verification_required'));
        }

        $notice = (string) config('accounts.verification.notice_url', '/');

        // Sending the user to the page they are on would loop.
        if ('/'.ltrim($request->path(), '/') === '/'.ltrim((string) parse_url($notice, PHP_URL_PATH), '/')) {
            return $next($request);
        }

        return redirect($notice)->with('accounts.status', [
            'kind' => 'error',
            'message' => __('accounts::messages.verification_required'),
        ]);
    }
}
