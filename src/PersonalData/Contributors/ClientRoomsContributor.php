<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-clientrooms: the coaching rooms held for the person (by
 * address or as owner), with their sittings, tasks, the person's answers and
 * the documents in them.
 *
 * Deleted through the room model when it is there: its `deleting` hook takes
 * the documents and recordings out of the asset container, which the foreign
 * key cascade cannot. Without the model, the rows go table by table.
 */
class ClientRoomsContributor extends TableContributor implements ErasesPersonalData
{
    public function key(): string
    {
        return 'clientrooms';
    }

    public function label(): string
    {
        return __('accounts::messages.section_clientrooms');
    }

    protected function marker(): string
    {
        return 'Goldnead\ClientRooms\Models\ClientRoom';
    }

    protected function tables(): array
    {
        return ['client_rooms'];
    }

    public function collect(User $user): array
    {
        $rooms = $this->rows('client_rooms', fn (Builder $q) => $this->whereRoom($q, $user));
        $roomIds = array_column($rooms, 'id');
        $tasks = $this->childRows('client_room_tasks', 'room_id', $roomIds);
        $submissions = $this->childRows('client_room_task_submissions', 'task_id', array_column($tasks, 'id'));

        return [
            'rooms' => $rooms,
            'sessions' => $this->childRows('client_room_sessions', 'room_id', $roomIds),
            'tasks' => $tasks,
            'task_submissions' => $submissions,
            'task_submission_files' => $this->childRows('client_room_task_submission_files', 'submission_id', array_column($submissions, 'id')),
            'files' => $this->childRows('client_room_files', 'room_id', $roomIds),
        ];
    }

    public function erase(User $user): ErasureResult
    {
        $roomIds = DB::table('client_rooms')->where(fn (Builder $q) => $this->whereRoom($q, $user))->pluck('id')->all();

        if ($roomIds === []) {
            return new ErasureResult($this->key());
        }

        $counts = $this->counts($roomIds);
        $model = $this->marker();

        if (is_subclass_of($model, Model::class)) {
            // Across brands: the brand scope would hide the rooms of another.
            foreach ($model::query()->withoutGlobalScopes()->whereIn('id', $roomIds)->get() as $room) {
                $room->delete();
            }
        } else {
            $this->deleteTables($roomIds);
        }

        return new ErasureResult($this->key(), deleted: array_filter($counts));
    }

    protected function whereRoom(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $w) => $this->whereEmail($w, 'email', $user)->orWhere('owner_user_id', (string) $user->id()));
    }

    /**
     * @param  list<int|string>  $roomIds
     * @return array<string, int>
     */
    protected function counts(array $roomIds): array
    {
        $taskIds = $this->ids('client_room_tasks', 'room_id', $roomIds);
        $submissionIds = $this->ids('client_room_task_submissions', 'task_id', $taskIds);

        return [
            'rooms' => count($roomIds),
            'sessions' => count($this->ids('client_room_sessions', 'room_id', $roomIds)),
            'tasks' => count($taskIds),
            'task_submissions' => count($submissionIds),
            'files' => count($this->ids('client_room_files', 'room_id', $roomIds))
                + count($this->ids('client_room_task_submission_files', 'submission_id', $submissionIds)),
        ];
    }

    /**
     * Children first: the keys cascade on MySQL and Postgres, not on a SQLite
     * connection without foreign key checks.
     *
     * @param  list<int|string>  $roomIds
     */
    protected function deleteTables(array $roomIds): void
    {
        $taskIds = $this->ids('client_room_tasks', 'room_id', $roomIds);
        $submissionIds = $this->ids('client_room_task_submissions', 'task_id', $taskIds);

        $this->deleteWhere('client_room_task_submission_files', fn (Builder $q) => $q->whereIn('submission_id', $submissionIds));
        $this->deleteWhere('client_room_task_submissions', fn (Builder $q) => $q->whereIn('id', $submissionIds));
        $this->deleteWhere('client_room_tasks', fn (Builder $q) => $q->whereIn('id', $taskIds));
        $this->deleteWhere('client_room_sessions', fn (Builder $q) => $q->whereIn('room_id', $roomIds));
        $this->deleteWhere('client_room_files', fn (Builder $q) => $q->whereIn('room_id', $roomIds));
        DB::table('client_rooms')->whereIn('id', $roomIds)->delete();
    }

    /**
     * @param  list<int|string>  $parents
     * @return list<int|string>
     */
    protected function ids(string $table, string $column, array $parents): array
    {
        if ($parents === [] || ! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->whereIn($column, $parents)->pluck('id')->all();
    }

    /**
     * @param  list<int|string>  $parents
     * @return list<array<string, mixed>>
     */
    protected function childRows(string $table, string $column, array $parents): array
    {
        if ($parents === [] || ! Schema::hasTable($table)) {
            return [];
        }

        return $this->rows($table, fn (Builder $q) => $q->whereIn($column, $parents));
    }
}
