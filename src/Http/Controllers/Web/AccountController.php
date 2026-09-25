<?php

namespace Goldnead\Accounts\Http\Controllers\Web;

use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Services\AccountDeletion;
use Goldnead\Accounts\Services\EmailChange;
use Goldnead\Accounts\Services\EmailVerification;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Services\PersonalDataExport;
use Goldnead\Accounts\Support\Users as User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Statamic\Auth\User as UserContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * The forms rendered by the `accounts:*` tags.
 *
 * Every form answers like Statamic's own user forms: a redirect back (or to
 * `_redirect`), success in the session, errors in a named error bag the tag
 * reads again.
 *
 * Changing the address, deleting the account and downloading its data ask
 * for Statamic's elevated session (`statamic.users.elevated_sessions_enabled`):
 * core's confirmation page, which takes the password, a passkey or a mailed
 * code, whatever the account has. Without one the visitor is sent there and
 * comes back to the page they were on. With elevated sessions switched off
 * in Statamic, there is no second confirmation, exactly as for core's own
 * sensitive actions. While an admin is signed in as the customer, the three
 * answer 403.
 */
class AccountController extends Controller
{
    public function __construct(protected Impersonation $impersonation) {}

    public function resendVerification(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $this->user();
        $verification->send($user);

        return $this->success($request, 'verify', __('accounts::messages.verification_sent'));
    }

    public function changeEmail(Request $request, EmailChange $emailChange): Response
    {
        $user = $this->user();

        if ($guard = $this->guard($request, url()->previous())) {
            return $guard;
        }

        try {
            $emailChange->request($user, (string) $request->input('email', ''));
        } catch (AccountException $e) {
            return $this->failure($request, 'change_email', $e->field, $e->getMessage());
        }

        return $this->success($request, 'change_email', __('accounts::messages.email_change_sent'));
    }

    public function cancelEmailChange(Request $request, EmailChange $emailChange): RedirectResponse
    {
        $user = $this->user();

        abort_if($this->impersonation->active(), 403, __('accounts::messages.impersonation_locked'));

        $emailChange->cancel($user);

        return $this->success($request, 'change_email', __('accounts::messages.email_change_cancelled'));
    }

    public function requestDeletion(Request $request, AccountDeletion $deletion): Response
    {
        $user = $this->user();

        if ($guard = $this->guard($request, url()->previous())) {
            return $guard;
        }

        try {
            $deletion->request($user);
        } catch (AccountException $e) {
            return $this->failure($request, 'delete', $e->field, $e->getMessage());
        }

        if (config('accounts.deletion.logout', false)) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success($request, 'delete', __('accounts::messages.deletion_scheduled'));
    }

    public function withdrawDeletion(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $user = $this->user();

        abort_if($this->impersonation->active(), 403, __('accounts::messages.impersonation_locked'));

        $open = $deletion->pending($user);
        $deletion->cancel($user);

        $message = __('accounts::messages.deletion_cancelled');

        // A cancellation made under the `cancel` policy before a failed run
        // is not undone by withdrawing. Say so.
        if ($open !== null && ($count = $deletion->subscriptionsCancelled($open)) > 0) {
            $message .= ' '.trans_choice('accounts::messages.subscriptions_stay_cancelled', $count, ['count' => $count]);
        }

        return $this->success($request, 'delete', $message);
    }

    public function export(Request $request, PersonalDataExport $export): Response
    {
        abort_unless(config('accounts.export.enabled', true), 404);

        $user = $this->user();

        // After the confirmation the browser comes straight back here and the
        // download starts.
        if ($guard = $this->guard($request, $request->fullUrl())) {
            return $guard;
        }

        $file = $export->build($user);

        return response()->download($file['path'], $file['filename'], ['Content-Type' => $file['mime']])
            ->deleteFileAfterSend();
    }

    protected function user(): UserContract
    {
        $user = User::current();

        abort_if($user === null, 403);

        return $user;
    }

    /**
     * 403 while impersonating, core's confirmation page without an elevated
     * session, null when the request may go on.
     */
    protected function guard(Request $request, string $returnTo): ?RedirectResponse
    {
        abort_if($this->impersonation->active(), 403, __('accounts::messages.impersonation_locked'));

        if (! config('statamic.users.elevated_sessions_enabled') || $request->hasElevatedSession()) {
            return null;
        }

        // Statamic registers its confirmation page only when elevated
        // sessions were on while the routes loaded. Switched on later, there
        // is nowhere to send the visitor: refuse, do not crash.
        abort_unless(Route::has('statamic.elevated-session'), 403, __('accounts::messages.elevation_unavailable'));

        $to = $request->input('_redirect');

        if (is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//')) {
            $returnTo = $to;
        }

        return redirect()->setIntendedUrl($returnTo)->to(route('statamic.elevated-session'));
    }

    protected function success(Request $request, string $form, string $message): RedirectResponse
    {
        return $this->back($request)->with('accounts.'.$form.'.success', $message);
    }

    protected function failure(Request $request, string $form, string $field, string $message): RedirectResponse
    {
        return $this->back($request)
            ->withInput()
            ->withErrors(new MessageBag([$field => $message]), 'accounts.'.$form);
    }

    protected function back(Request $request): RedirectResponse
    {
        $to = $request->input('_redirect');

        // Only a path on this site. An absolute URL in a form field is an
        // open redirect.
        if (is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//')) {
            return redirect($to);
        }

        return redirect()->back();
    }
}
