<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Statamic\Contracts\Auth\User;

/**
 * goldnead/statamic-payments: payments with their items, subscriptions,
 * withdrawals and cancellations. Payments keys everything by the address
 * the buyer typed, there is no user id to join on.
 */
class PaymentsContributor extends TableContributor
{
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
}
