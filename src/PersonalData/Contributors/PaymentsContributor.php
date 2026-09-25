<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;
use Throwable;

/**
 * goldnead/statamic-payments: payments with their items, subscriptions,
 * withdrawals and cancellations. Payments keys everything by the address
 * the buyer typed, there is no user id to join on.
 *
 * On deletion nothing here is touched. Payment records are accounting
 * documents: § 147 AO and § 14b UStG require keeping them for ten years, name
 * and address included. What the deletion does check is that no
 * subscription is still charging: an account that disappears while a
 * provider keeps billing leaves the person with no way to cancel.
 */
class PaymentsContributor extends TableContributor implements ErasesPersonalData
{
    /**
     * Subscription states in which the provider still charges, or will again.
     *
     * @var list<string>
     */
    public const RUNNING = ['pending', 'active', 'paused', 'suspended'];

    public const SUBSCRIPTION_MODEL = 'Goldnead\StatamicPayments\Models\Subscription';

    public const SUBSCRIPTIONS_SERVICE = 'Goldnead\StatamicPayments\Support\Subscriptions';

    public function key(): string
    {
        return 'payments';
    }

    public function label(): string
    {
        return __('accounts::messages.section_payments');
    }

    protected function marker(): string
    {
        return 'Goldnead\StatamicPayments\Models\Payment';
    }

    protected function tables(): array
    {
        return ['payments', 'subscriptions'];
    }

    public function collect(User $user): array
    {
        $payments = $this->rows('payments', fn (Builder $q) => $this->whereEmail($q, 'email', $user));
        $ids = array_column($payments, 'id');

        $data = [
            'payments' => $payments,
            'payment_items' => $ids === [] || ! Schema::hasTable('payment_items')
                ? []
                : $this->rows('payment_items', fn (Builder $q) => $q->whereIn('payment_id', $ids)),
            'subscriptions' => $this->rows('subscriptions', fn (Builder $q) => $this->whereEmail($q, 'email', $user)),
        ];

        foreach (['payment_withdrawals' => 'withdrawals', 'payment_cancellations' => 'cancellations'] as $table => $key) {
            $data[$key] = Schema::hasTable($table)
                ? $this->rows($table, fn (Builder $q) => $this->whereEmail($q, 'email', $user))
                : [];
        }

        return $data;
    }

    /**
     * @return list<array{id: int, product: string}>
     */
    public function running(User $user): array
    {
        $rows = $this->rows('subscriptions', fn (Builder $q) => $this->whereEmail($q, 'email', $user)->whereIn('status', self::RUNNING));

        return array_map(fn (array $row) => ['id' => (int) $row['id'], 'product' => (string) $row['product']], $rows);
    }

    public function blockers(User $user): array
    {
        $running = $this->running($user);

        if ($running === []) {
            return [];
        }

        $portal = $this->portalUrl();

        return array_map(fn (array $subscription) => $portal === null
            ? __('accounts::messages.blocker_subscription', ['product' => $subscription['product']])
            : __('accounts::messages.blocker_subscription_portal', ['product' => $subscription['product'], 'url' => $portal]),
            $running);
    }

    /**
     * Cancel every running subscription through payments' own service, which
     * tells the provider first and writes what the provider answered. Returns
     * the subscriptions that could not be cancelled, as blockers.
     *
     * @return list<string>
     */
    public function cancelRunning(User $user): array
    {
        $failed = [];

        foreach ($this->running($user) as $subscription) {
            if (! class_exists(self::SUBSCRIPTION_MODEL) || ! class_exists(self::SUBSCRIPTIONS_SERVICE)) {
                $failed[] = __('accounts::messages.blocker_subscription_cancel_failed', ['product' => $subscription['product']]);

                continue;
            }

            try {
                $model = (self::SUBSCRIPTION_MODEL)::find($subscription['id']);
                $cancelled = $model !== null && app(self::SUBSCRIPTIONS_SERVICE)->cancel($model);
            } catch (Throwable $e) {
                Log::warning('statamic-accounts: cancelling a subscription before deletion failed.', [
                    'subscription' => $subscription['id'],
                    'exception' => $e->getMessage(),
                ]);
                $cancelled = false;
            }

            if (! $cancelled) {
                $failed[] = __('accounts::messages.blocker_subscription_cancel_failed', ['product' => $subscription['product']]);
            }
        }

        return $failed;
    }

    public function erase(User $user): ErasureResult
    {
        $byEmail = fn (Builder $q) => $this->whereEmail($q, 'email', $user);

        return new ErasureResult($this->key(), retained: array_filter([
            'payments' => $this->countWhere('payments', $byEmail),
            'subscriptions' => $this->countWhere('subscriptions', $byEmail),
            'withdrawals' => $this->countWhere('payment_withdrawals', $byEmail),
            'cancellations' => $this->countWhere('payment_cancellations', $byEmail),
        ]), note: __('accounts::messages.retained_payments'));
    }

    public function portalUrl(): ?string
    {
        $configured = config('accounts.deletion.portal_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return Route::has('statamic-payments.portal.request') ? route('statamic-payments.portal.request') : null;
    }
}
