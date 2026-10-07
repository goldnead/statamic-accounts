<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;

/**
 * goldnead/statamic-courses: enrolments, lesson progress and the progress
 * events under the user id, and course team seats (by the owner's id or the
 * member's address).
 */
class CoursesContributor extends TableContributor implements ErasesPersonalData
{
    /**
     * @var array<string, string>
     */
    protected const BY_USER = [
        'courses_enrollments' => 'enrollments',
        'courses_lesson_states' => 'lesson_states',
        'courses_lesson_events' => 'lesson_events',
    ];

    public function key(): string
    {
        return 'courses';
    }

    public function label(): string
    {
        return __('accounts::messages.section_courses');
    }

    protected function marker(): string
    {
        return 'Goldnead\Courses\Models\LessonState';
    }

    protected function tables(): array
    {
        return ['courses_lesson_states'];
    }

    public function collect(User $user): array
    {
        $data = [];

        foreach (self::BY_USER as $table => $key) {
            $data[$key] = Schema::hasTable($table)
                ? $this->rows($table, fn (Builder $q) => $q->where('user_id', (string) $user->id()))
                : [];
        }

        $data['team_seats'] = Schema::hasTable('courses_team_members')
            ? $this->rows('courses_team_members', fn (Builder $q) => $this->whereSeat($q, $user))
            : [];

        return $data;
    }

    /**
     * Progress goes. Of the team seats, the ones the person holds in someone
     * else's team go, and so do the seats of a team they bought: the grant
     * behind it is erased with the entitlements, so those seats lead nowhere.
     */
    public function erase(User $user): ErasureResult
    {
        $deleted = [];

        foreach (self::BY_USER as $table => $key) {
            $deleted[$key] = $this->deleteWhere($table, fn (Builder $q) => $q->where('user_id', (string) $user->id()));
        }

        $deleted['team_seats'] = $this->deleteWhere('courses_team_members', fn (Builder $q) => $this->whereSeat($q, $user));

        return new ErasureResult($this->key(), deleted: array_filter($deleted));
    }

    protected function whereSeat(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $w) => $this->whereEmail($w, 'email', $user)->orWhere('owner_id', (string) $user->id()));
    }
}
