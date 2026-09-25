<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-invoices: the invoices issued to the address, with their
 * lines. Exported, and on deletion kept: an issued invoice is a tax document
 * (§ 14b UStG, § 147 AO), its buyer fields included.
 */
class InvoicesContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'invoices';
    }

    public function label(): string
    {
        return __('accounts::messages.section_invoices');
    }

    protected function marker(): string
    {
        return 'Goldnead\Invoices\Models\Invoice';
    }

    protected function tables(): array
    {
        return ['invoices'];
    }

    public function collect(User $user): array
    {
        $invoices = $this->rows('invoices', fn (Builder $q) => $this->whereEmail($q, 'buyer_email', $user));
        $ids = array_column($invoices, 'id');

        return [
            'invoices' => $invoices,
            'invoice_items' => $ids === [] || ! Schema::hasTable('invoice_items')
                ? []
                : $this->rows('invoice_items', fn (Builder $q) => $q->whereIn('invoice_id', $ids)),
        ];
    }

    public function erase(User $user): ErasureResult
    {
        return new ErasureResult($this->key(), retained: array_filter([
            'invoices' => $this->countWhere('invoices', fn (Builder $q) => $this->whereEmail($q, 'buyer_email', $user)),
        ]), note: __('accounts::messages.retained_invoices'));
    }
}
