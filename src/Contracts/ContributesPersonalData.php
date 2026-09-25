<?php

namespace Goldnead\Accounts\Contracts;

use Statamic\Contracts\Auth\User;

/**
 * One addon's share of a customer's personal data export.
 *
 * Register an implementation with `Accounts::contributeData(MyContributor::class)`
 * or tag it `accounts.personal-data` in the container. Each contributor
 * becomes one `<key>.json` in the export.
 */
interface ContributesPersonalData
{
    /**
     * File name in the export, without `.json`. Lowercase, stable.
     */
    public function key(): string;

    /**
     * Shown in the Control Panel next to the export.
     */
    public function label(): string;

    /**
     * Whether the data source exists on this site (addon installed, table
     * migrated). An unavailable contributor is left out, not exported empty.
     */
    public function available(): bool;

    /**
     * Everything this addon holds about the user, as JSON-encodable arrays.
     * Leave out secrets (password hashes, tokens) and internal hashes that
     * mean nothing to the person.
     *
     * @return array<string, mixed>
     */
    public function collect(User $user): array;
}
