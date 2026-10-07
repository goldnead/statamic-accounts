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
 * code, whatever the account has. Without one the visitor is sent there; a
 * form's action is remembered and runs when the confirmation comes back
 * (`resume()`), the download starts again by itself. With elevated sessions switched off
 * in Statamic, there is no second confirmation, exactly as for core's own
 * sensitive actions. While an admin is signed in as the customer, the three
 * answer 403.
 */
class AccountController extends Controller
{
    /** Session key of the action waiting for the confirmation. */
    public const PENDING = 'accounts.pending_action';

    /** How long a remembered action may wait for its confirmation. */
    public const PENDING_MINUTES = 30;

    protected const DELETE = 'delete';

    protected const CHANGE_EMAIL = 'change_email';

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
        $email = (string) $request->input('email', '');

        if ($guard = $this->guard($request, url()->previous(), ['action' => self::CHANGE_EMAIL, 'email' => $email])) {
            return $guard;
        }

        return $this->runChangeEmail($user, $emailChange, $email, $this->returnTo($request));
    }

    public function requestDeletion(Request $request, AccountDeletion $deletion): Response
    {
        $user = $this->user();

        if ($guard = $this->guard($request, url()->previous(), ['action' => self::DELETE])) {
            return $guard;
        }

        return $this->runDeletion($request, $user, $deletion, $this->returnTo($request));
    }

    /**
     * Where core's confirmation page sends the visitor after a form needed
     * it: the action they had asked for runs now, once, and they land where
     * the form would have sent them. Without this they came back to the page
     * and had to press the same button a second time.
     *
     * Only what a form put into the session (behind CSRF) can run here; a
     * link to this URL alone does nothing. The remembered action lasts
     * `PENDING_MINUTES`.
     */
    public function resume(Request $request, AccountDeletion $deletion, EmailChange $emailChange): RedirectResponse
    {
        $user = $this->user();
        $pending = $request->session()->get(self::PENDING);

        if (! is_array($pending) || ! is_string($pending['action'] ?? null)) {
            return redirect('/');
        }

        $returnTo = $this->safePath($pending['return'] ?? null) ?? '/';

        // Not confirmed (yet): back to the confirmation, the action waits.
        if ($guard = $this->guard($request, route('statamic.accounts.resume'))) {
            return $guard;
        }

        $request->session()->forget(self::PENDING);

        if ((int) ($pending['at'] ?? 0) < now()->subMinutes(self::PENDING_MINUTES)->getTimestamp()) {
            return redirect($returnTo);
        }

        return match ($pending['action']) {
            self::DELETE => $this->runDeletion($request, $user, $deletion, $returnTo),
            self::CHANGE_EMAIL => $this->runChangeEmail($user, $emailChange, (string) ($pending['email'] ?? ''), $returnTo),
            default => redirect($returnTo),
        };
    }

    protected function runChangeEmail(UserContract $user, EmailChange $emailChange, string $email, string $returnTo): RedirectResponse
    {
        try {
            $emailChange->request($user, $email);
        } catch (AccountException $e) {
            return $this->failure($returnTo, 'change_email', $e->field, $e->getMessage(), ['email' => $email]);
        }

        return $this->success($returnTo, 'change_email', __('accounts::messages.email_change_sent'));
    }

    protected function runDeletion(Request $request, UserContract $user, AccountDeletion $deletion, string $returnTo): RedirectResponse
    {
        try {
            $deletion->request($user);
        } catch (AccountException $e) {
            return $this->failure($returnTo, 'delete', $e->field, $e->getMessage());
        }

        if (config('accounts.deletion.logout', false)) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success($returnTo, 'delete', __('accounts::messages.deletion_scheduled'));
    }

    public function cancelEmailChange(Request $request, EmailChange $emailChange): RedirectResponse
    {
        $user = $this->user();

        abort_if($this->impersonation->active(), 403, __('accounts::messages.impersonation_locked'));

        $emailChange->cancel($user);

        return $this->success($request, 'change_email', __('accounts::messages.email_change_cancelled'));
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
     *
     * With `$remember` (a form's action), the action is kept in the session
     * and the confirmation returns to `resume()`, which runs it. Without it
     * (the download), the confirmation returns to `$returnTo`.
     *
     * @param  array<string, string>|null  $remember
     */
    protected function guard(Request $request, string $returnTo, ?array $remember = null): ?RedirectResponse
    {
        abort_if($this->impersonation->active(), 403, __('accounts::messages.impersonation_locked'));

        if (! config('statamic.users.elevated_sessions_enabled') || $request->hasElevatedSession()) {
            return null;
        }

        // Statamic registers its confirmation page only when elevated
        // sessions were on while the routes loaded. Switched on later, there
        // is nowhere to send the visitor: refuse, do not crash.
        abort_unless(Route::has('statamic.elevated-session'), 403, __('accounts::messages.elevation_unavailable'));

        if ($remember !== null) {
            $request->session()->put(self::PENDING, $remember + [
                'return' => $this->returnTo($request),
                'at' => now()->getTimestamp(),
            ]);
            $returnTo = route('statamic.accounts.resume');
        } elseif ($to = $this->safePath($request->input('_redirect'))) {
            $returnTo = $to;
        }

        return redirect()->setIntendedUrl($returnTo)->to(route('statamic.elevated-session'));
    }

    /**
     * Where a form goes afterwards: its `_redirect`, else the page it was
     * sent from, as a path on this site.
     */
    protected function returnTo(Request $request): string
    {
        if ($to = $this->safePath($request->input('_redirect'))) {
            return $to;
        }

        $previous = url()->previous();
        $host = parse_url($previous, PHP_URL_HOST);

        if (is_string($host) && $host !== $request->getHost()) {
            return '/';
        }

        $path = (string) (parse_url($previous, PHP_URL_PATH) ?: '/');
        $query = parse_url($previous, PHP_URL_QUERY);

        return $this->safePath($path.(is_string($query) && $query !== '' ? '?'.$query : '')) ?? '/';
    }

    /**
     * Only a path on this site. An absolute URL in a form field is an open
     * redirect.
     */
    protected function safePath(mixed $to): ?string
    {
        return is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_starts_with($to, '/\\')
            ? $to
            : null;
    }

    protected function success(Request|string $to, string $form, string $message): RedirectResponse
    {
        return $this->back($to)->with('accounts.'.$form.'.success', $message);
    }

    /**
     * @param  array<string, mixed>  $input  what to refill when `$to` is a path, not the request
     */
    protected function failure(Request|string $to, string $form, string $field, string $message, array $input = []): RedirectResponse
    {
        $response = $this->back($to);

        return ($to instanceof Request ? $response->withInput() : $response->withInput($input))
            ->withErrors(new MessageBag([$field => $message]), 'accounts.'.$form);
    }

    protected function back(Request|string $to): RedirectResponse
    {
        if (is_string($to)) {
            return redirect($to);
        }

        if ($path = $this->safePath($to->input('_redirect'))) {
            return redirect($path);
        }

        return redirect()->back();
    }
}
