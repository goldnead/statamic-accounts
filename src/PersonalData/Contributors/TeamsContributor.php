<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-teams: the teams the user belongs to, with role and date.
 *
 * A docking point. Should statamic-teams register a contributor of its own
 * under the key `teams`, that one replaces this (the registry keys by
 * `key()`, later wins).
 */
class TeamsContributor extends TableContributor implements ErasesPersonalData
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

    /**
     * Teams the person holds alone: they own it (by `owner_id` or an
     * `owner` role), and nobody else does.
     *
     * @return list<array{id: int, name: string, others: int}>
     */
    public function soleOwnerships(User $user): array
    {
        $id = (string) $user->id();

        $teams = DB::table('teams')
            ->where('owner_id', $id)
            ->orWhereIn('id', DB::table('team_members')->where('user_id', $id)->where('role', 'owner')->select('team_id'))
            ->get(['id', 'name', 'owner_id']);

        $sole = [];

        foreach ($teams as $team) {
            $otherOwners = DB::table('team_members')
                ->where('team_id', $team->id)
                ->where('user_id', '!=', $id)
                ->where('role', 'owner')
                ->count();

            if ($otherOwners > 0) {
                continue;
            }

            $sole[] = [
                'id' => (int) $team->id,
                'name' => (string) $team->name,
                'others' => DB::table('team_members')->where('team_id', $team->id)->where('user_id', '!=', $id)->count(),
            ];
        }

        return $sole;
    }

    /**
     * A team with other members cannot lose its last owner: the members
     * would be left in a team nobody can manage or pay for.
     */
    public function blockers(User $user, string $audience = 'customer'): array
    {
        return array_values(array_map(
            fn (array $team) => __('accounts::messages.blocker_team_owner'.($audience === 'admin' ? '_admin' : ''), ['team' => $team['name'], 'count' => $team['others']]),
            array_filter($this->soleOwnerships($user), fn (array $team) => $team['others'] > 0),
        ));
    }

    /**
     * Teams held alone and without other members go with the person. Every
     * other membership is removed; an ownership shared with another owner
     * passes to that owner. Invitations to the address are deleted, the
     * person's name comes off invitations they sent.
     */
    public function erase(User $user): ErasureResult
    {
        $id = (string) $user->id();
        $deleted = ['teams' => 0, 'memberships' => 0, 'invitations' => 0];
        $anonymized = ['invitations_sent' => 0, 'ownerships_passed' => 0];

        foreach ($this->soleOwnerships($user) as $team) {
            if ($team['others'] > 0) {
                continue;
            }

            foreach (['team_members', 'team_invitations', 'team_roles'] as $table) {
                $count = $this->deleteWhere($table, fn ($q) => $q->where('team_id', $team['id']));

                if ($table === 'team_members') {
                    $deleted['memberships'] += $count;
                } elseif ($table === 'team_invitations') {
                    $deleted['invitations'] += $count;
                }
            }

            $deleted['teams'] += DB::table('teams')->where('id', $team['id'])->delete();
        }

        // Owned together with somebody else: that owner takes the row.
        foreach (DB::table('teams')->where('owner_id', $id)->pluck('id') as $teamId) {
            $next = DB::table('team_members')->where('team_id', $teamId)->where('user_id', '!=', $id)->where('role', 'owner')->value('user_id');
            DB::table('teams')->where('id', $teamId)->update(['owner_id' => $next]);
            $anonymized['ownerships_passed']++;
        }

        $deleted['memberships'] += DB::table('team_members')->where('user_id', $id)->delete();
        $deleted['invitations'] += $this->deleteWhere('team_invitations', fn ($q) => $q->whereRaw('lower(email) = ?', [$this->email($user)]));

        if (Schema::hasTable('team_invitations')) {
            foreach (['invited_by', 'accepted_by'] as $column) {
                if (Schema::hasColumn('team_invitations', $column)) {
                    $anonymized['invitations_sent'] += DB::table('team_invitations')->where($column, $id)->update([$column => null]);
                }
            }
        }

        return new ErasureResult($this->key(), deleted: array_filter($deleted), anonymized: array_filter($anonymized));
    }
}
