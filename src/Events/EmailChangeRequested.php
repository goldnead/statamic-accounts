<?php

namespace Goldnead\Accounts\Events;

/**
 * The customer asked for a new address. Nothing on the account has changed
 * yet; `email` is still the old one.
 */
class EmailChangeRequested extends AccountEvent
{
    public function __construct(string $userId, string $email, ?string $name, public readonly string $newEmail)
    {
        parent::__construct($userId, $email, $name);
    }

    public static function handle(): string
    {
        return 'accounts.email_change.requested';
    }

    protected function extra(): array
    {
        return ['new_email' => $this->newEmail];
    }
}
