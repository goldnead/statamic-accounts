<?php

namespace Goldnead\Accounts\Http\Controllers\Web;

use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Services\AccountDeletion;
use Goldnead\Accounts\Services\EmailChange;
use Goldnead\Accounts\Services\EmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * The links in the mails. The route has already checked the signature and
 * the expiry; an invalid link never reaches these methods (Laravel answers
 * 403). What is checked here is what a valid signature cannot prove: that
 * the address in the link is still the one on the account, that the request
 * is still open.
 */
class LinkController extends Controller
{
    public function verify(EmailVerification $verification, string $user, string $hash): RedirectResponse
    {
        $verified = $verification->verify($user, $hash);

        return $this->done(
            'accounts.verification.redirect',
            $verified === null ? 'error' : 'success',
            $verified === null ? __('accounts::messages.link_invalid') : __('accounts::messages.email_verified'),
        );
    }

    public function confirmEmail(EmailChange $emailChange, int $request, string $hash): RedirectResponse
    {
        try {
            $emailChange->confirm($request, $hash);
        } catch (AccountException $e) {
            return $this->done('accounts.email_change.redirect', 'error', $e->getMessage());
        }

        return $this->done('accounts.email_change.redirect', 'success', __('accounts::messages.email_changed'));
    }

    public function cancelDeletion(AccountDeletion $deletion, int $request): RedirectResponse
    {
        $record = AccountRequest::query()->ofType(AccountRequest::TYPE_DELETION)->find($request);

        $cancelled = $record !== null && $deletion->cancelRequest($record);

        return $this->done(
            'accounts.deletion.redirect',
            $cancelled ? 'success' : 'error',
            $cancelled ? __('accounts::messages.deletion_cancelled') : __('accounts::messages.link_invalid'),
        );
    }

    /**
     * Back to the site with the outcome in the session, where the
     * `accounts:status` tag shows it.
     */
    protected function done(string $redirectKey, string $kind, string $message): RedirectResponse
    {
        return redirect((string) config($redirectKey, '/'))
            ->with('accounts.status', ['kind' => $kind, 'message' => $message]);
    }
}
