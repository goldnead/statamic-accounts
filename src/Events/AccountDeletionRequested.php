<?php

namespace Goldnead\Accounts\Events;

/**
 * Deletion is scheduled. The account still exists and the request can be
 * withdrawn until `scheduled_for`.
 */
class AccountDeletionRequested extends AccountEvent
{
    public function __construct(string $userId, string $email, ?string $name, public readonly string $scheduledFor)
    {
        parent::__construct($userId, $email, $name);
    }

    public static function handle(): string
    {
        return 'accounts.deletion.requested';
    }

    protected function extra(): array
    {
        return ['scheduled_for' => $this->scheduledFor];
    }
}
