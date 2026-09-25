<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Statamic\Contracts\Auth\User;

/**
 * goldnead/statamic-notifications: the notifications addressed to the user,
 * their channel preferences and the digests sent to them.
 */
class NotificationsContributor extends TableContributor
{
    public function key(): string
    {
        return 'notifications';
    }

    public function label(): string
    {
        return __('accounts::messages.section_notifications');
    }

    protected function marker(): string
    {
        return 'Goldnead\Notifications\Models\NotificationItem';
    }

    protected function tables(): array
    {
        return ['notification_items'];
    }

    public function collect(User $user): array
    {
        $byUser = fn (Builder $q) => $q->where(fn (Builder $w) => $this->whereEmailIfPresent($w->where('user_id', (string) $user->id()), $user));

        $data = ['notifications' => $this->rows('notification_items', $byUser)];

        foreach (['notification_preferences' => 'preferences', 'notification_digest_runs' => 'digests'] as $table => $key) {
            $data[$key] = Schema::hasTable($table)
                ? $this->rows($table, fn (Builder $q) => $q->where('user_id', (string) $user->id()))
                : [];
        }

        return $data;
    }

    protected function whereEmailIfPresent(Builder $query, User $user): Builder
    {
        if (! Schema::hasColumn('notification_items', 'email')) {
            return $query;
        }

        return $query->orWhereRaw('lower(email) = ?', [$this->email($user)]);
    }
}
