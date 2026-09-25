<?php

namespace Goldnead\Accounts\PersonalData\Contributors;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Statamic\Auth\User;

/**
 * The account itself: the user record without its secrets, plus the open
 * requests this addon holds for it.
 */
class AccountContributor implements ContributesPersonalData, ErasesPersonalData
{
    public function blockers(User $user): array
    {
        return [];
    }

    /**
     * This addon's own rows: address changes go, deletion requests stay as
     * the record of the deletion, with the address blacked out. The user
     * record itself is deleted by the caller after every eraser has run.
     */
    public function erase(User $user): ErasureResult
    {
        $mine = fn () => AccountRequest::query()->forUser((string) $user->id());

        $deleted = $mine()->where('type', '!=', AccountRequest::TYPE_DELETION)->delete();
        $blacked = $mine()->whereNotNull('email')->update(['email' => null]);

        return new ErasureResult($this->key(), deleted: array_filter(['requests' => $deleted]), anonymized: array_filter(['requests' => $blacked]));
    }

    /**
     * Keys that are secrets or security material, not information about the
     * person.
     */
    protected const SECRET_PATTERN = '/password|token|secret|two_factor|recovery|passkey|webauthn/i';

    public function key(): string
    {
        return 'account';
    }

    public function label(): string
    {
        return __('accounts::messages.section_account');
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(User $user): array
    {
        $data = collect($user->data()->all())
            ->reject(fn ($value, $key) => preg_match(self::SECRET_PATTERN, (string) $key) === 1)
            ->all();

        return [
            'id' => (string) $user->id(),
            'email' => (string) $user->email(),
            'name' => $user->name(),
            'roles' => $user->roles()->map->handle()->values()->all(),
            'groups' => $user->groups()->map->handle()->values()->all(),
            'data' => $data,
            'requests' => AccountRequest::query()
                ->forUser((string) $user->id())
                ->orderBy('id')
                ->get(['type', 'email', 'status', 'due_at', 'resolved_at', 'created_at'])
                ->map(fn (AccountRequest $request) => [
                    'type' => $request->type,
                    'email' => $request->email,
                    'status' => $request->status,
                    'due_at' => $request->due_at?->toIso8601String(),
                    'resolved_at' => $request->resolved_at?->toIso8601String(),
                    'created_at' => $request->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
