<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Statamic\Contracts\Auth\User;

/**
 * goldnead/statamic-leadhub: the CRM contact (by normalised address or by the
 * user id stored on it) with its timeline, notes, follow-ups and revenue.
 *
 * Reads the database driver's tables. A site on leadhub's flat-file driver has
 * no tables and this contributor reports itself unavailable; that site's CRM
 * data has to be exported from leadhub itself.
 */
class LeadhubContributor extends TableContributor
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
}
