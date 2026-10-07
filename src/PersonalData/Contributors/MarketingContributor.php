<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-marketing: the person's list subscriptions (newsletter
 * and every other list), the campaign mails sent to them with their opens
 * and clicks, and the frequency-cap log.
 *
 * Matched by the normalised address, across every brand.
 */
class MarketingContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'marketing';
    }

    public function label(): string
    {
        return __('accounts::messages.section_marketing');
    }

    protected function marker(): string
    {
        return 'Goldnead\Marketing\Models\Subscription';
    }

    protected function tables(): array
    {
        return ['marketing_subscriptions'];
    }

    public function collect(User $user): array
    {
        $subscriptions = $this->rows('marketing_subscriptions', fn (Builder $q) => $q->where('email_normalized', $this->email($user)));
        $messages = $this->messageRows($user, array_column($subscriptions, 'id'));

        return [
            'subscriptions' => $subscriptions,
            'messages' => $messages,
            'message_events' => $messages === [] || ! Schema::hasTable('marketing_message_events')
                ? []
                : $this->rows('marketing_message_events', fn (Builder $q) => $q->whereIn('message_id', array_column($messages, 'id'))),
        ];
    }

    /**
     * Subscriptions, the mails sent to them, their opens and clicks and the
     * cap log go. Deleted rather than unsubscribed: an unsubscribed row still
     * holds the address. Keeping someone from being mailed again is the
     * suppression list's job, which this eraser leaves alone.
     */
    public function erase(User $user): ErasureResult
    {
        $subscriptionIds = DB::table('marketing_subscriptions')
            ->where('email_normalized', $this->email($user))
            ->pluck('id')
            ->all();

        $messageIds = array_column($this->messageRows($user, $subscriptionIds), 'id');

        // Children first: the foreign keys cascade on MySQL and Postgres, but
        // not on a SQLite connection without foreign key checks.
        $events = $messageIds === [] ? 0 : $this->deleteWhere('marketing_message_events', fn (Builder $q) => $q->whereIn('message_id', $messageIds));
        $messages = $messageIds === [] ? 0 : $this->deleteWhere('marketing_messages', fn (Builder $q) => $q->whereIn('id', $messageIds));

        return new ErasureResult($this->key(), deleted: array_filter([
            'subscriptions' => DB::table('marketing_subscriptions')->whereIn('id', $subscriptionIds)->delete(),
            'messages' => $messages,
            'message_events' => $events,
            'mail_log' => $this->deleteWhere('marketing_mail_log', fn (Builder $q) => $q->where('email_normalized', $this->email($user))),
        ]));
    }

    /**
     * Mails sent to one of the subscriptions or to the address itself
     * (transactional sends without a subscription carry only the address).
     *
     * @param  list<int|string>  $subscriptionIds
     * @return list<array<string, mixed>>
     */
    protected function messageRows(User $user, array $subscriptionIds): array
    {
        if (! Schema::hasTable('marketing_messages')) {
            return [];
        }

        return $this->rows('marketing_messages', fn (Builder $q) => $q->where(function (Builder $w) use ($user, $subscriptionIds) {
            $this->whereEmail($w, 'email', $user);

            if ($subscriptionIds !== []) {
                $w->orWhereIn('subscription_id', $subscriptionIds);
            }
        }));
    }
}
