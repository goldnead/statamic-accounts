<?php

namespace Goldnead\Accounts\Http\Controllers\Web;

use Goldnead\Accounts\Support\Users as User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\ViewErrorBag;

/**
 * Statamic's confirmation page (elevated session) as a page of the site,
 * not of the Control Panel.
 *
 * Core renders its own page in Control Panel style. A site points
 * `statamic.users.elevated_sessions_url` here, and the form posts to core's
 * own `statamic.elevated-session.confirm`, so core still checks the password
 * or the mailed code and still decides where to go afterwards. Only the look
 * changes: `accounts::confirm` extends `accounts::layout`, which a site
 * replaces under `resources/views/vendor/accounts/layout.blade.php`.
 *
 * Password and mailed code. An account that may only confirm with a passkey
 * (`statamic.webauthn.allow_password_login_with_passkey` off) is told that
 * this page cannot do it; such a site keeps core's page.
 *
 * An account with a password is offered the code as well (`?by=code`). People
 * who sign in by link often hold a password nobody knows (a placeholder set
 * when the account was created), and core would ask for exactly that. Core's
 * confirmation accepts a mailed code from any account, so this is only the
 * page offering it; core still checks it.
 */
class ConfirmController extends Controller
{
    public function show(Request $request): View
    {
        $user = User::current();

        abort_if($user === null, 403);

        $method = $user->getElevatedSessionMethod();
        $byCode = $method === 'password_confirmation' && $request->query('by') === 'code';

        if ($byCode) {
            $method = 'verification_code';
        }

        if ($method === 'verification_code') {
            $request->session()->sendElevatedSessionVerificationCodeIfRequired();
        }

        $errors = $request->session()->get('errors');
        $bag = $errors instanceof ViewErrorBag ? $errors->getBag('user.elevated_session') : null;
        $pending = $request->session()->get(AccountController::PENDING);

        return view('accounts::confirm', [
            'method' => $method,
            'email' => $user->email(),
            'submit_url' => route('statamic.elevated-session.confirm'),
            // Core refuses to resend to an account that has a password.
            'resend_url' => $byCode ? route('statamic.accounts.confirm.code') : route('statamic.elevated-session.resend-code'),
            'code_url' => $method === 'password_confirmation' ? route('statamic.accounts.confirm.code') : null,
            'password_url' => $byCode ? route('statamic.accounts.confirm') : null,
            'status' => $request->session()->get('status'),
            'error' => $bag?->first(),
            'action' => is_array($pending) ? ($pending['action'] ?? null) : null,
            'cancel_url' => is_array($pending) && is_string($pending['return'] ?? null) ? $pending['return'] : url()->previous('/'),
        ]);
    }

    /**
     * Mail a fresh code to an account that has a password and show the code
     * field. Nothing else changes; the code is checked by core.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $user = User::current();

        abort_if($user === null, 403);

        if ($user->getElevatedSessionMethod() === 'password_confirmation') {
            $request->session()->sendElevatedSessionVerificationCode();

            return redirect()
                ->route('statamic.accounts.confirm', ['by' => 'code'])
                ->with('status', __('statamic::messages.elevated_session_verification_code_sent'));
        }

        return redirect()->route('statamic.accounts.confirm');
    }
}
