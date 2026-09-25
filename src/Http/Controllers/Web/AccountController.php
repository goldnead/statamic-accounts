<?php

namespace Goldnead\Accounts\Http\Controllers\Web;

use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Services\AccountDeletion;
use Goldnead\Accounts\Services\EmailChange;
use Goldnead\Accounts\Services\EmailVerification;
use Goldnead\Accounts\Services\PersonalDataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The forms rendered by the `accounts:*` tags.
 *
 * Every form answers like Statamic's own user forms: a redirect back (or to
 * `_redirect`), success in the session, errors in a named error bag the tag
 * reads again. Changing the address and deleting the account ask for the
 * current password, the same way core's password form does.
 */
class AccountController extends Controller
{
    public function resendVerification(Request $request, EmailVerification $verification): RedirectResponse
    {
        $user = $this->user();
        $verification->send($user);

        return $this->success($request, 'verify', __('accounts::messages.verification_sent'));
    }

    public function changeEmail(Request $request, EmailChange $emailChange): RedirectResponse
    {
        $user = $this->user();

        if ($failed = $this->checkPassword($request, $user, 'change_email')) {
            return $failed;
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
        $emailChange->cancel($this->user());

        return $this->success($request, 'change_email', __('accounts::messages.email_change_cancelled'));
    }

    public function requestDeletion(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $user = $this->user();

        if ($failed = $this->checkPassword($request, $user, 'delete')) {
            return $failed;
        }

        $deletion->request($user);

        if (config('accounts.deletion.logout', false)) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success($request, 'delete', __('accounts::messages.deletion_scheduled'));
    }

    public function withdrawDeletion(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $deletion->cancel($this->user());

        return $this->success($request, 'delete', __('accounts::messages.deletion_cancelled'));
    }

    public function export(Request $request, PersonalDataExport $export): BinaryFileResponse
    {
        abort_unless(config('accounts.export.enabled', true), 404);

        $file = $export->build($this->user());

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
     * The current password, when the account has one. An account without
     * (passkey or OAuth only) confirms by typing its own address instead.
     */
    protected function checkPassword(Request $request, UserContract $user, string $form): ?RedirectResponse
    {
        $hash = $user->password();

        if (filled($hash)) {
            return Hash::check((string) $request->input('password', ''), (string) $hash)
                ? null
                : $this->failure($request, $form, 'password', __('accounts::messages.password_wrong'));
        }

        return mb_strtolower(trim((string) $request->input('confirm', ''))) === mb_strtolower((string) $user->email())
            ? null
            : $this->failure($request, $form, 'confirm', __('accounts::messages.confirm_wrong'));
    }

    protected function success(Request $request, string $form, string $message): RedirectResponse
    {
        return $this->back($request)->with('accounts.'.$form.'.success', $message);
    }

    protected function failure(Request $request, string $form, string $field, string $message): RedirectResponse
    {
        return $this->back($request)
            ->withInput($request->except(['password', 'confirm']))
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
