<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Statamic\Auth\User;

/**
 * goldnead/statamic-offers: seats the person holds in a seat pool, and the
 * pools they bought.
 *
 * A pool bought by the person with nobody else seated goes. A pool with
 * other people in it stays for them (their seats and the access behind them
 * are theirs): it loses the buyer's address and name, and its management
 * link stops working. The payment behind the pool stays with payments.
 */
class OffersContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'offers';
    }

    public function label(): string
    {
        return __('accounts::messages.section_offers');
    }

    protected function marker(): string
    {
        return 'Goldnead\StatamicOffers\Models\SeatPool';
    }

    protected function tables(): array
    {
        return ['offer_seat_pools', 'offer_seats'];
    }

    public function collect(User $user): array
    {
        $pools = $this->rows('offer_seat_pools', fn (Builder $q) => $this->whereEmail($q, 'owner_email', $user));

        return [
            'seats' => $this->rows('offer_seats', fn (Builder $q) => $this->whereEmail($q, 'email', $user)),
            'pools' => $pools,
            // Who the person seated: their own data as the buyer.
            'pool_seats' => $pools === [] ? [] : $this->rows('offer_seats', fn (Builder $q) => $q->whereIn('pool_id', array_column($pools, 'id'))),
        ];
    }

    public function erase(User $user): ErasureResult
    {
        $seats = $this->deleteWhere('offer_seats', fn (Builder $q) => $this->whereEmail($q, 'email', $user));

        $deletedPools = 0;
        $anonymisedPools = 0;

        foreach (DB::table('offer_seat_pools')->where(fn (Builder $q) => $this->whereEmail($q, 'owner_email', $user))->pluck('id') as $poolId) {
            if (DB::table('offer_seats')->where('pool_id', $poolId)->exists()) {
                DB::table('offer_seat_pools')->where('id', $poolId)->update([
                    'owner_email' => '',
                    'owner_name' => null,
                    // A link nobody holds: the old one in the buyer's mail
                    // would still open the pool.
                    'manage_token' => Str::random(64),
                ]);
                $anonymisedPools++;

                continue;
            }

            DB::table('offer_seat_pools')->where('id', $poolId)->delete();
            $deletedPools++;
        }

        return new ErasureResult(
            $this->key(),
            deleted: array_filter(['seats' => $seats, 'pools' => $deletedPools]),
            anonymized: array_filter(['pools' => $anonymisedPools]),
            note: $anonymisedPools > 0 ? __('accounts::messages.offers_pools_note') : null,
        );
    }
}
