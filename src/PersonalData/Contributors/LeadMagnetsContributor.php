<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-lead-magnets: the free downloads requested with the
 * person's address and each time one was downloaded.
 */
class LeadMagnetsContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'lead_magnets';
    }

    public function label(): string
    {
        return __('accounts::messages.section_lead_magnets');
    }

    protected function marker(): string
    {
        return 'Goldnead\LeadMagnets\Models\Grant';
    }

    protected function tables(): array
    {
        return ['lead_magnet_grants'];
    }

    public function collect(User $user): array
    {
        $grants = $this->rows('lead_magnet_grants', fn (Builder $q) => $this->whereEmail($q, 'email', $user));
        $ids = array_column($grants, 'id');

        return [
            'grants' => $grants,
            'downloads' => $ids === [] || ! Schema::hasTable('lead_magnet_downloads')
                ? []
                : $this->rows('lead_magnet_downloads', fn (Builder $q) => $q->whereIn('grant_id', $ids)),
        ];
    }

    /**
     * Grants and their downloads go. The access grant a lead magnet also
     * writes into entitlements is erased by the entitlements eraser (it is
     * held by the address).
     */
    public function erase(User $user): ErasureResult
    {
        $ids = $this->whereEmail(DB::table('lead_magnet_grants'), 'email', $user)->pluck('id')->all();

        $downloads = $ids === [] ? 0 : $this->deleteWhere('lead_magnet_downloads', fn (Builder $q) => $q->whereIn('grant_id', $ids));
        $grants = DB::table('lead_magnet_grants')->whereIn('id', $ids)->delete();

        return new ErasureResult($this->key(), deleted: array_filter([
            'grants' => $grants,
            'downloads' => $downloads,
        ]));
    }
}
