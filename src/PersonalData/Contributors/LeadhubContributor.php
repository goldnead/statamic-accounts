<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-leadhub: the CRM contact (by normalised address or by the
 * user id stored on it) with its timeline, notes, follow-ups and revenue.
 *
 * Reads the database driver's tables. A site on leadhub's flat-file driver has
 * no tables and this contributor reports itself unavailable; that site's CRM
 * data has to be exported from leadhub itself.
 */
class LeadhubContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'leadhub';
    }

    public function label(): string
    {
        return __('accounts::messages.section_leadhub');
    }

    protected function marker(): string
    {
        return 'Goldnead\Leadhub\Models\Contact';
    }

    protected function tables(): array
    {
        return ['leadhub_contacts'];
    }

    public function collect(User $user): array
    {
        $contacts = $this->rows('leadhub_contacts', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('email_normalized', $this->email($user))
            ->orWhere('user_id', (string) $user->id())));

        $ids = array_column($contacts, 'id');
        $data = ['contacts' => $contacts];

        foreach (['leadhub_events' => 'events', 'leadhub_notes' => 'notes', 'leadhub_followups' => 'followups', 'leadhub_contact_revenue' => 'revenue'] as $table => $key) {
            $data[$key] = $ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'contact_id')
                ? []
                : $this->rows($table, fn (Builder $q) => $q->whereIn('contact_id', $ids));
        }

        return $data;
    }

    /**
     * Tables that hang off a contact by `contact_id`. Deleted with it.
     *
     * @var list<string>
     */
    protected const CONTACT_TABLES = ['leadhub_events', 'leadhub_notes', 'leadhub_followups', 'leadhub_tasks', 'leadhub_contact_revenue', 'leadhub_contact_segments', 'leadhub_contact_tags'];

    /**
     * The contact goes, with its timeline, notes, follow-ups, tasks and
     * revenue lines. Deleted rather than anonymised: an anonymous contact
     * with a purchase history is still a profile, and the revenue it carries
     * is a copy of what payments keeps anyway.
     */
    public function erase(User $user): ErasureResult
    {
        $ids = DB::table('leadhub_contacts')
            ->where(fn (Builder $w) => $w->where('email_normalized', $this->email($user))->orWhere('user_id', (string) $user->id()))
            ->pluck('id')
            ->all();

        $deleted = [];

        if ($ids !== []) {
            foreach (self::CONTACT_TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'contact_id')) {
                    $deleted[str_replace('leadhub_', '', $table)] = DB::table($table)->whereIn('contact_id', $ids)->delete();
                }
            }
        }

        $deleted['contacts'] = DB::table('leadhub_contacts')->whereIn('id', $ids)->delete();

        return new ErasureResult($this->key(), deleted: array_filter($deleted));
    }
}
