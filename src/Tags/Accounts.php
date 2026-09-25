<?php

namespace Goldnead\Accounts\Tags;

use Goldnead\Accounts\Services\AccountDeletion;
use Goldnead\Accounts\Services\EmailChange;
use Goldnead\Accounts\Services\EmailVerification;
use Goldnead\Accounts\Services\Impersonation;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Statamic\Facades\User;
use Statamic\Tags\Concerns\RendersForms;
use Statamic\Tags\Tags;

/**
 * Frontend tags, shaped like Statamic's `user:*` tags: a form tag renders
 * `<form>` with CSRF around its contents and hands them `success`, `errors`
 * and `error`; a `redirect` parameter chooses where to go after submitting.
 *
 * ```antlers
 * {{ accounts:verify_notice }}…{{ /accounts:verify_notice }}
 * {{ accounts:change_email_form }}…{{ /accounts:change_email_form }}
 * {{ accounts:delete_form }}…{{ /accounts:delete_form }}
 * <a href="{{ accounts:export_url }}">…</a>
 * {{ accounts:status }}…{{ /accounts:status }}
 * {{ accounts:impersonating }}…{{ /accounts:impersonating }}
 * ```
 */
class Accounts extends Tags
{
    use RendersForms;

    protected static $handle = 'accounts';

    /**
     * `{{ accounts:verified }}`: true when the signed-in user's address is
     * confirmed. False for guests.
     */
    public function verified(): bool
    {
        $user = User::current();

        return $user !== null && app(EmailVerification::class)->isVerified($user);
    }

    /**
     * Renders its contents only for a signed-in user whose address is not
     * confirmed, as a form that sends the link again.
     *
     * Variables: `email`, `success`, `errors`, `error`.
     */
    public function verifyNotice(): string
    {
        $user = User::current();
        $verification = app(EmailVerification::class);

        if ($user === null || ! $verification->enabled() || $verification->isVerified($user)) {
            return '';
        }

        return $this->form('verify', route('statamic.accounts.verification.resend'), [
            'email' => $user->email(),
        ]);
    }

    /**
     * Form for a new address. Fields: `email` (the new one) and `password`
     * (or `confirm` with the current address for accounts without one).
     *
     * Variables: `email`, `pending_email`, `pending_expires`, `has_password`,
     * `cancel_url` (POST target to withdraw a pending change), `success`,
     * `errors`, `error`, `old`.
     */
    public function changeEmailForm(): string
    {
        $user = User::current();

        if ($user === null) {
            return '';
        }

        $pending = app(EmailChange::class)->pending($user);

        return $this->form('change_email', route('statamic.accounts.email.change'), [
            'email' => $user->email(),
            'pending_email' => $pending?->email,
            'pending_expires' => $pending?->due_at?->isoFormat('LLL'),
            'has_password' => filled($user->password()),
            'cancel_url' => route('statamic.accounts.email.cancel'),
        ]);
    }

    /**
     * Form to schedule the account's deletion. Same confirmation fields as
     * the address form. While a deletion is pending, `pending` is true and
     * the form posts to the withdrawal instead.
     *
     * Variables: `pending`, `scheduled_for`, `grace_days`, `has_password`,
     * `success`, `errors`, `error`.
     */
    public function deleteForm(): string
    {
        $user = User::current();

        if ($user === null) {
            return '';
        }

        $deletion = app(AccountDeletion::class);
        $pending = $deletion->pending($user);

        $action = $pending === null
            ? route('statamic.accounts.deletion.request')
            : route('statamic.accounts.deletion.withdraw');

        return $this->form('delete', $action, [
            'pending' => $pending !== null,
            'scheduled_for' => $pending?->due_at?->isoFormat('LL'),
            'grace_days' => $deletion->graceDays(),
            'has_password' => filled($user->password()),
        ]);
    }

    /**
     * `{{ accounts:export_url }}`: the download of the signed-in user's own
     * data. The route checks the session; the URL itself carries no secret.
     */
    public function exportUrl(): string
    {
        return route('statamic.accounts.export');
    }

    /**
     * The outcome of a mail link (confirmed, expired, withdrawn). Renders its
     * contents with `kind` (`success`/`error`) and `message` when there is
     * one, nothing otherwise.
     */
    public function status(): string
    {
        $status = session('accounts.status');

        if (! is_array($status)) {
            return '';
        }

        return (string) $this->parse($status);
    }

    /**
     * Renders its contents while an admin is signed in as this customer,
     * with `stop_url` (core's "Stop impersonating") and `impersonator`.
     */
    public function impersonating(): string
    {
        $impersonation = app(Impersonation::class);

        if (! $impersonation->active()) {
            return '';
        }

        $admin = User::find((string) $impersonation->impersonatorId());

        return (string) $this->parse([
            'stop_url' => cp_route('impersonation.stop'),
            'impersonator' => $admin?->name() ?? $admin?->email(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function form(string $name, string $action, array $data): string
    {
        $errors = $this->errorBag($name);

        $data = array_merge($data, [
            'success' => session('accounts.'.$name.'.success'),
            'errors' => $errors->all(),
            'error' => $errors->toArray() === [] ? [] : array_map(fn ($messages) => $messages[0], $errors->toArray()),
            'old' => old(),
        ]);

        $params = [];

        if ($redirect = $this->params->get('redirect')) {
            $params['redirect'] = $redirect;
        }

        $html = $this->formOpen($action, 'POST', ['redirect']);
        $html .= $this->formMetaFields($params);
        $html .= $this->parse($data);
        $html .= $this->formClose();

        return $html;
    }

    protected function errorBag(string $name): MessageBag
    {
        $errors = session('errors');

        if (! $errors instanceof ViewErrorBag) {
            return new MessageBag;
        }

        return $errors->getBag('accounts.'.$name);
    }
}
