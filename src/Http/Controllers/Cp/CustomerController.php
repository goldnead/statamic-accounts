<?php

namespace Goldnead\Accounts\Http\Controllers\Cp;

use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Services\AccountDeletion;
use Goldnead\Accounts\Services\CustomerOverview;
use Goldnead\Accounts\Services\EmailVerification;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Services\PersonalDataExport;
use Goldnead\Accounts\Support\Schema as AccountsSchema;
use Goldnead\Accounts\Support\Users;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Auth\User as UserContract;
use Statamic\CP\Column;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The customer list and the customer overview.
 *
 * Every route carries `can:` middleware and each method checks again: the
 * middleware is what a reader of routes/cp.php can verify, the check here is
 * what survives the route being registered somewhere else.
 */
class CustomerController extends CpController
{
    /**
     * How many users the list loads. The listing searches and sorts in the
     * browser; a site with more users finds the rest through the search in
     * core's Users screen and opens the overview from there.
     */
    public const LIMIT = 1000;

    public function index(Request $request, EmailVerification $verification, AccountDeletion $deletion): Response
    {
        $this->authorize('view accounts');

        $ready = Schema::hasTable('account_requests');

        if (! $ready) {
            Log::warning('statamic-accounts: the account_requests table is missing; run php artisan migrate.');
        }

        $pendingDeletions = $ready
            ? AccountRequest::query()
                ->ofType(AccountRequest::TYPE_DELETION)
                ->open()
                ->get(['user_id', 'due_at', 'status', 'meta'])
                ->keyBy('user_id')
            : collect();

        $users = User::query()->orderBy('email')->limit(self::LIMIT)->get();

        $rows = $users->map(function (UserContract $user) use ($verification, $pendingDeletions) {
            $open = $pendingDeletions->get((string) $user->id());

            return [
                'id' => (string) $user->id(),
                'email' => (string) $user->email(),
                'name' => $user->name(),
                'verified' => $verification->isVerified($user),
                'deletion_due' => $open?->due_at?->isoFormat('L'),
                'deletion_blocked' => $open?->status === AccountRequest::STATUS_BLOCKED,
                // Since when it is blocked, which is not when it was due.
                'deletion_blocked_since' => isset($open?->meta['blocked_at']) ? Carbon::parse($open->meta['blocked_at'])->isoFormat('L') : null,
                'url' => cp_route('accounts.customers.show', (string) $user->id()),
            ];
        })->values()->all();

        return Inertia::render('accounts::Customers/Index', [
            'customers' => $rows,
            'columns' => [
                Column::make('email')->label(__('Email')),
                Column::make('name')->label(__('Name')),
                Column::make('verified')->label(__('accounts::messages.column_verified')),
                Column::make('deletion_due')->label(__('accounts::messages.column_deletion')),
            ],
            'wiringUrl' => cp_route('accounts.wiring'),
            'total' => User::query()->count(),
            'setupRequired' => ! $ready,
            't' => Arr::except((array) trans('accounts::cp'), 'labels'),
        ]);
    }

    public function show(Request $request, CustomerOverview $overview, Impersonation $impersonation, string $user): Response
    {
        $this->authorize('view accounts');

        $customer = $this->find($user);
        $me = Users::current();

        return Inertia::render('accounts::Customers/Show', [
            'overview' => AccountsSchema::ready() ? $overview->for($customer) : ['account' => $this->bare($customer)],
            'urls' => [
                'index' => cp_route('accounts.index'),
                'edit' => cp_route('users.edit', (string) $customer->id()),
                'export' => cp_route('accounts.customers.export', (string) $customer->id()),
                'resend' => cp_route('accounts.customers.verification.resend', (string) $customer->id()),
                'verify' => cp_route('accounts.customers.verification.mark', (string) $customer->id()),
                'deletion' => cp_route('accounts.customers.deletion.schedule', (string) $customer->id()),
                'cancelDeletion' => cp_route('accounts.customers.deletion.cancel', (string) $customer->id()),
                'impersonate' => cp_route('accounts.customers.impersonate', (string) $customer->id()),
            ],
            'can' => [
                'manage' => $me?->can('manage accounts') ?? false,
                'delete' => $this->mayDelete($customer),
                'export' => ($me?->can('export account data') ?? false) && config('accounts.export.enabled', true),
                'impersonate' => $me !== null && $impersonation->allowed($me, $customer),
                'edit' => $me?->can('edit', $customer) ?? false,
            ],
            'graceDays' => (int) config('accounts.deletion.grace_days', 14),
            't' => Arr::except((array) trans('accounts::cp'), 'labels'),
        ]);
    }

    public function export(PersonalDataExport $export, string $user): BinaryFileResponse
    {
        $this->authorize('export account data');
        abort_unless(config('accounts.export.enabled', true), 404);

        $file = $export->build($this->find($user), 'admin', Users::current());

        return response()->download($file['path'], $file['filename'], ['Content-Type' => $file['mime']])
            ->deleteFileAfterSend();
    }

    public function resendVerification(EmailVerification $verification, string $user): RedirectResponse
    {
        $this->authorize('manage accounts');

        $verification->send($this->find($user))
            ? Toast::success(__('accounts::messages.verification_sent'))
            : Toast::info(__('accounts::messages.already_verified'));

        return back();
    }

    public function markVerified(EmailVerification $verification, string $user): RedirectResponse
    {
        $this->authorize('manage accounts');

        $customer = $this->find($user);

        if (! $verification->isVerified($customer)) {
            $verification->markVerified($customer, Users::current());
        }

        Toast::success(__('accounts::messages.email_verified'));

        return back();
    }

    /**
     * Scheduling a deletion is deleting a user: core's `delete users`
     * (UserPolicy::delete) decides, and a super admin is only deleted by a
     * super admin.
     */
    public function scheduleDeletion(AccountDeletion $deletion, string $user): RedirectResponse
    {
        $customer = $this->find($user);

        abort_unless($this->mayDelete($customer), 403);

        try {
            $deletion->request($customer, Users::current());
        } catch (AccountException $e) {
            Toast::error($e->getMessage());

            return back();
        }

        Toast::success(__('accounts::messages.deletion_scheduled'));

        return back();
    }

    protected function mayDelete(UserContract $customer): bool
    {
        $me = Users::current();

        if ($me === null || (string) $me->id() === (string) $customer->id()) {
            return false;
        }

        if ($customer->isSuper() && ! $me->isSuper()) {
            return false;
        }

        return $me->can('delete', $customer);
    }

    public function cancelDeletion(AccountDeletion $deletion, string $user): RedirectResponse
    {
        $this->authorize('manage accounts');

        $deletion->cancel($this->find($user), Users::current());
        Toast::success(__('accounts::messages.deletion_cancelled'));

        return back();
    }

    /**
     * Core's impersonation, started from here. Core's own action asks for an
     * elevated session; so does this.
     */
    public function impersonate(Impersonation $impersonation, string $user): \Symfony\Component\HttpFoundation\Response
    {
        $customer = $this->find($user);
        $me = Users::current();

        abort_unless($me !== null && $impersonation->allowed($me, $customer), 403);

        $this->requireElevatedSession();

        return Inertia::location($impersonation->start($me, $customer));
    }

    protected function find(string $id): UserContract
    {
        $user = Users::find($id);

        abort_if($user === null, 404);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function bare(UserContract $user): array
    {
        return ['id' => (string) $user->id(), 'email' => (string) $user->email(), 'name' => $user->name()];
    }
}
