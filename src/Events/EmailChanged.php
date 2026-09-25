<?php

namespace Goldnead\Accounts\Events;

/**
 * The new address was confirmed and is now the account's address. `email` is
 * the new one, `old_email` the one it replaced: a CRM keyed on the address
 * needs both to move the contact instead of creating a second one.
 */
class EmailChanged extends AccountEvent
{
    public function __construct(string $userId, string $email, ?string $name, public readonly string $oldEmail)
    {
        parent::__construct($userId, $email, $name);
    }

    public static function handle(): string
    {
        return 'accounts.email.changed';
    }

    protected function extra(): array
    {
        return ['old_email' => $this->oldEmail];
    }
}
