<?php

namespace Goldnead\Accounts\Contracts;

use Goldnead\Accounts\PersonalData\ErasureResult;
use Statamic\Auth\User;

/**
 * One addon's share of deleting a person: the counterpart of
 * {@see ContributesPersonalData}.
 *
 * Register with `Accounts::eraseData(MyEraser::class)` or tag it
 * `accounts.personal-data-erasers`. Erasers run inside one database
 * transaction, while the user still exists, right before the user is deleted.
 */
interface ErasesPersonalData
{
    /**
     * Stable key; it names this eraser's part in the deletion record.
     */
    public function key(): string;

    public function label(): string;

    public function available(): bool;

    /**
     * Reasons the account cannot be deleted yet, as sentences. Empty means
     * go. Asked when the deletion is requested and again when it is due.
     * `$audience` is `customer` (addressed as "you") or `admin` (the account
     * in the third person, for the Control Panel).
     *
     * @return list<string>
     */
    public function blockers(User $user, string $audience = 'customer'): array;

    /**
     * Delete or anonymise this addon's data about the user, and say what was
     * deleted, anonymised and kept. Keep nothing in the result that names the
     * person: it is stored after they are gone.
     */
    public function erase(User $user): ErasureResult;
}
