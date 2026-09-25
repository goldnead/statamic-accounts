<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Statamic\Auth\User;

/**
 * goldnead/statamic-activity: the ledger entries about the person.
 *
 * The ledger is append-only; its one way to strip personal fields is its own
 * `activity:anonymize --user=<id>`, which keeps the fact (type, time) and
 * clears user id, actor, properties and context. This eraser runs exactly
 * that. What the ledger's API cannot reach, and this eraser therefore does
 * not touch: entries recorded under another user id that name the person in
 * their properties (an admin's entry about them). The note in the deletion
 * record says so.
 */
class ActivityContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'activity';
    }

    public function label(): string
    {
        return __('accounts::messages.section_activity');
    }

    protected function marker(): string
    {
        return 'Goldnead\Activity\Facades\Activity';
    }

    protected function tables(): array
    {
        return ['activities'];
    }

    public function available(): bool
    {
        return parent::available() && array_key_exists('activity:anonymize', Artisan::all());
    }

    public function collect(User $user): array
    {
        return ['activities' => $this->rows('activities', fn (Builder $q) => $q->where('user_id', (string) $user->id()))];
    }

    public function erase(User $user): ErasureResult
    {
        $count = $this->countWhere('activities', fn (Builder $q) => $q->where('user_id', (string) $user->id())->where('anonymized', false));

        Artisan::call('activity:anonymize', ['--user' => (string) $user->id()]);

        return new ErasureResult($this->key(), anonymized: array_filter(['activities' => $count]), note: __('accounts::messages.activity_note'));
    }
}
