<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Auth\User;
use Throwable;

/**
 * Reads a sibling addon's tables directly.
 *
 * On purpose not through the sibling's models: those carry a brand scope, and
 * a person's data export covers every brand of the site, not the one the
 * request happens to be in. Reading tables also means the export needs no
 * change in the sibling and survives its model being renamed.
 */
abstract class TableContributor implements ContributesPersonalData
{
    /**
     * Columns never exported: secrets, and hashes that mean nothing to the
     * person and only help whoever holds the file.
     *
     * @var list<string>
     */
    protected const HIDDEN = ['ip_hash', 'token', 'token_hash', 'password', 'secret', 'remember_token', 'visit_token'];

    /**
     * The sibling's class whose presence means "installed".
     */
    abstract protected function marker(): string;

    /**
     * @return list<string>
     */
    abstract protected function tables(): array;

    public function available(): bool
    {
        if (! class_exists($this->marker())) {
            return false;
        }

        try {
            foreach ($this->tables() as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Nothing stands in the way unless a subclass says so.
     *
     * @return list<string>
     */
    public function blockers(User $user, string $audience = 'customer'): array
    {
        return [];
    }

    /**
     * Delete the matching rows of a table, if the table exists.
     *
     * @param  callable(Builder): mixed  $where
     */
    protected function deleteWhere(string $table, callable $where): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $where($query);

        return $query->delete();
    }

    /**
     * Count the matching rows of a table, if the table exists.
     *
     * @param  callable(Builder): mixed  $where
     */
    protected function countWhere(string $table, callable $where): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $where($query);

        return $query->count();
    }

    /**
     * @param  callable(Builder): mixed  $where
     * @return list<array<string, mixed>>
     */
    protected function rows(string $table, callable $where): array
    {
        $query = DB::table($table);
        $where($query);

        return $query->orderBy('id')->get()
            ->map(fn ($row) => $this->clean((array) $row))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function clean(array $row): array
    {
        foreach (array_keys($row) as $column) {
            if (in_array($column, self::HIDDEN, true)) {
                unset($row[$column]);

                continue;
            }

            // JSON columns come back as strings from the query builder.
            if (is_string($row[$column]) && in_array($row[$column][0] ?? '', ['{', '['], true)) {
                $decoded = json_decode($row[$column], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$column] = $decoded;
                }
            }
        }

        return $row;
    }

    protected function email(User $user): string
    {
        return mb_strtolower(trim((string) $user->email()));
    }

    /**
     * Case-insensitive match on an email column. Siblings store the address
     * as it was typed.
     */
    protected function whereEmail(Builder $query, string $column, User $user): Builder
    {
        return $query->whereRaw('lower('.$query->getGrammar()->wrap($column).') = ?', [$this->email($user)]);
    }
}
