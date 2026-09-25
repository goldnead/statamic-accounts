<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\PersonalData\Contributors\EntitlementsContributor;
use Goldnead\Accounts\PersonalData\Contributors\PaymentsContributor;
use Goldnead\Accounts\PersonalData\Contributors\TeamsContributor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;
use Throwable;

/**
 * Everything the suite knows about one customer, for the "Customer" screen
 * in the Control Panel and for any caller that wants the same picture.
 *
 * Each section says whether its addon is installed. A missing addon is a
 * section with `installed: false`, not an empty list: "no payments" and
 * "payments is not installed" are different answers.
 */
class CustomerOverview
{
    public function __construct(
        protected EmailVerification $verification,
        protected EmailChange $emailChange,
        protected AccountDeletion $deletion,
        protected ActivityBridge $activity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        return [
            'account' => $this->account($user),
            'payments' => $this->payments($user),
            'subscriptions' => $this->subscriptions($user),
            'entitlements' => $this->entitlements($user),
            'teams' => $this->teams($user),
            'activity' => $this->activity($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function account(User $user): array
    {
        $change = $this->emailChange->pending($user);
        $deletion = $this->deletion->pending($user);

        return [
            'id' => (string) $user->id(),
            'email' => (string) $user->email(),
            'name' => $user->name(),
            'initials' => $user->initials(),
            'avatar' => $user->avatar(64),
            'super' => $user->isSuper(),
            'roles' => $user->roles()->map(fn ($role) => $role->title())->values()->all(),
            'verified' => $this->verification->isVerified($user),
            'verified_at' => $this->date($this->verification->verifiedAt($user)),
            'last_login' => $this->date($user->lastLogin()),
            'pending_email' => $change?->email,
            'pending_email_expires' => $this->date($change?->due_at),
            'deletion_due' => $this->date($deletion?->due_at),
        ];
    }

    /**
     * @return array{installed: bool, rows: list<array<string, mixed>>}
     */
    public function payments(User $user): array
    {
        $contributor = app(PaymentsContributor::class);

        if (! $contributor->available()) {
            return ['installed' => false, 'rows' => []];
        }

        $rows = $this->guard(fn () => DB::table('payments')
            ->whereRaw('lower(email) = ?', [mb_strtolower((string) $user->email())])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'product' => $row->product,
                'amount' => $this->money((int) $row->amount_cent, (string) $row->currency),
                'status' => $row->status,
                'provider' => $row->provider,
                'date' => $this->date($row->paid_at ?? $row->created_at),
            ])
            ->all());

        return ['installed' => true, 'rows' => $rows];
    }

    /**
     * @return array{installed: bool, rows: list<array<string, mixed>>}
     */
    public function subscriptions(User $user): array
    {
        if (! app(PaymentsContributor::class)->available()) {
            return ['installed' => false, 'rows' => []];
        }

        $rows = $this->guard(fn () => DB::table('subscriptions')
            ->whereRaw('lower(email) = ?', [mb_strtolower((string) $user->email())])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'product' => $row->product,
                'amount' => $this->money((int) $row->amount_cent, (string) $row->currency),
                'interval' => $row->interval,
                'status' => $row->status,
                'next_payment' => $this->date($row->next_payment_at),
                'started' => $this->date($row->starts_at ?? $row->created_at),
            ])
            ->all());

        return ['installed' => true, 'rows' => $rows];
    }

    /**
     * @return array{installed: bool, rows: list<array<string, mixed>>}
     */
    public function entitlements(User $user): array
    {
        if (! app(EntitlementsContributor::class)->available()) {
            return ['installed' => false, 'rows' => []];
        }

        $rows = $this->guard(fn () => EntitlementsContributor::whereSubject(DB::table('entitlements'), $user)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'product' => $row->product_slug,
                'source' => $row->source,
                'status' => $row->status,
                'held_by' => $row->subject_type === 'email' ? 'email' : 'user',
                'starts' => $this->date($row->starts_at),
                'expires' => $this->date($row->expires_at),
            ])
            ->all());

        return ['installed' => true, 'rows' => $rows];
    }

    /**
     * @return array{installed: bool, rows: list<array<string, mixed>>}
     */
    public function teams(User $user): array
    {
        if (! app(TeamsContributor::class)->available()) {
            return ['installed' => false, 'rows' => []];
        }

        $rows = $this->guard(fn () => array_map(fn (array $row) => array_merge($row, [
            'joined_at' => $this->date($row['joined_at']),
        ]), TeamsContributor::memberships($user)));

        return ['installed' => true, 'rows' => $rows];
    }

    /**
     * The last entries in the activity ledger about this user, when the
     * ledger is there.
     *
     * @return array{installed: bool, rows: list<array<string, mixed>>}
     */
    public function activity(User $user): array
    {
        if (! $this->activity->available()) {
            return ['installed' => false, 'rows' => []];
        }

        $rows = $this->guard(function () use ($user) {
            if (! Schema::hasTable('activities')) {
                return [];
            }

            $id = (string) $user->id();

            return DB::table('activities')
                ->where(function ($q) use ($id) {
                    $q->where('user_id', $id)
                        ->orWhere('properties', 'like', '%"user_id":"'.addcslashes($id, '%_').'"%');
                })
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'type' => $row->event_type ?? null,
                    'date' => $this->date($row->occurred_at ?? $row->created_at ?? null),
                ])
                ->all();
        });

        return ['installed' => true, 'rows' => $rows];
    }

    /**
     * A sibling whose schema moved must not take the whole screen down.
     *
     * @param  callable(): list<array<string, mixed>>  $read
     * @return list<array<string, mixed>>
     */
    protected function guard(callable $read): array
    {
        try {
            return $read();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    protected function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            $date = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }

        return $date->isoFormat('L');
    }

    protected function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.strtoupper($currency);
    }
}
