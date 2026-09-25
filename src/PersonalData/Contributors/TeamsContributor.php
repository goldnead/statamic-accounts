<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Illuminate\Support\Facades\DB;
use Statamic\Contracts\Auth\User;

/**
 * goldnead/statamic-teams: the teams the user belongs to, with role and date.
 *
 * A docking point. Should statamic-teams register a contributor of its own
 * under the key `teams`, that one replaces this (the registry keys by
 * `key()`, later wins).
 */
class TeamsContributor extends TableContributor
{
    public function key(): string
    {
        return 'teams';
    }

    public function label(): string
    {
        return __('accounts::messages.section_teams');
    }

    protected function marker(): string
    {
        return 'Goldnead\Teams\Models\Team';
    }

    protected function tables(): array
    {
        return ['teams', 'team_members'];
    }

    public function collect(User $user): array
    {
        return ['memberships' => static::memberships($user)];
    }

    /**
     * @return list<array{team_id: mixed, team: mixed, type: mixed, role: mixed, owner: bool, joined_at: mixed}>
     */
    public static function memberships(User $user): array
    {
        return DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('team_members.user_id', (string) $user->id())
            ->orderBy('teams.name')
            ->get(['teams.id', 'teams.name', 'teams.type', 'teams.owner_id', 'team_members.role', 'team_members.joined_at'])
            ->map(fn ($row) => [
                'team_id' => $row->id,
                'team' => $row->name,
                'type' => $row->type,
                'role' => $row->role,
                'owner' => (string) $row->owner_id === (string) $user->id(),
                'joined_at' => $row->joined_at,
            ])
            ->all();
    }
}
