<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-funnels: the person's visits through a funnel (by the
 * address they entered), the steps they took and the funnel mails sent to
 * them. The payment a visit led to stays with payments.
 */
class FunnelsContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'funnels';
    }

    public function label(): string
    {
        return __('accounts::messages.section_funnels');
    }

    protected function marker(): string
    {
        return 'Goldnead\StatamicFunnels\Models\FunnelVisit';
    }

    protected function tables(): array
    {
        return ['funnel_visits'];
    }

    public function collect(User $user): array
    {
        $visits = $this->rows('funnel_visits', fn (Builder $q) => $this->whereEmail($q, 'email', $user));
        $ids = array_column($visits, 'id');

        return [
            'visits' => $visits,
            'step_events' => $ids === [] || ! Schema::hasTable('funnel_step_events')
                ? []
                : $this->rows('funnel_step_events', fn (Builder $q) => $q->whereIn('visit_id', $ids)),
            'mail_deliveries' => Schema::hasTable('funnel_mail_deliveries')
                ? $this->rows('funnel_mail_deliveries', fn (Builder $q) => $this->whereDelivery($q, $user, $ids))
                : [],
        ];
    }

    public function erase(User $user): ErasureResult
    {
        $ids = DB::table('funnel_visits')->where(fn (Builder $q) => $this->whereEmail($q, 'email', $user))->pluck('id')->all();

        // Children first, see MarketingContributor.
        $deliveries = $this->deleteWhere('funnel_mail_deliveries', fn (Builder $q) => $this->whereDelivery($q, $user, $ids));
        $events = $ids === [] ? 0 : $this->deleteWhere('funnel_step_events', fn (Builder $q) => $q->whereIn('visit_id', $ids));
        $visits = $ids === [] ? 0 : DB::table('funnel_visits')->whereIn('id', $ids)->delete();

        return new ErasureResult($this->key(), deleted: array_filter([
            'visits' => $visits,
            'step_events' => $events,
            'mail_deliveries' => $deliveries,
        ]));
    }

    /**
     * Mails of the person's visits, and any other sent to the address.
     *
     * @param  list<int|string>  $visitIds
     */
    protected function whereDelivery(Builder $query, User $user, array $visitIds): Builder
    {
        return $query->where(function (Builder $w) use ($user, $visitIds) {
            $this->whereEmail($w, 'to', $user);

            if ($visitIds !== []) {
                $w->orWhereIn('visit_id', $visitIds);
            }
        });
    }
}
