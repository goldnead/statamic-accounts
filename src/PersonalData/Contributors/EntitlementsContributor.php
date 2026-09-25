<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Support\Subjects;
use Illuminate\Database\Query\Builder;
use Statamic\Auth\User;

/**
 * goldnead/statamic-entitlements: every grant held by the user, whether it
 * was stored under the user (a course, a manual grant) or under the address
 * (a purchase through payments).
 */
class EntitlementsContributor extends TableContributor
{
    public function key(): string
    {
        return 'entitlements';
    }

    public function label(): string
    {
        return __('accounts::messages.section_entitlements');
    }

    protected function marker(): string
    {
        return 'Goldnead\Entitlements\Models\Entitlement';
    }

    protected function tables(): array
    {
        return ['entitlements'];
    }

    public function collect(User $user): array
    {
        return ['entitlements' => $this->rows('entitlements', fn (Builder $q) => static::whereSubject($q, $user))];
    }

    public static function whereSubject(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where(fn (Builder $email) => $email
                ->where('subject_type', 'email')
                ->where('subject_id', mb_strtolower(trim((string) $user->email()))))
                ->orWhere(fn (Builder $own) => $own
                    ->whereIn('subject_type', Subjects::userTypes())
                    ->where('subject_id', (string) $user->id()));
        });
    }
}
