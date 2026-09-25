<?php

namespace Goldnead\Accounts\Events;

/**
 * A deletion was due, but something stands in the way (a running
 * subscription, a team with other members). The account stays; the person
 * was told why. `reasons` is how many, not what: the sentences name products
 * and teams and stay out of webhook bodies.
 */
class AccountDeletionBlocked extends AccountEvent
{
    public function __construct(string $userId, string $email, ?string $name, public readonly int $reasons)
    {
        parent::__construct($userId, $email, $name);
    }

    public static function handle(): string
    {
        return 'accounts.deletion.blocked';
    }

    protected function extra(): array
    {
        return ['reasons' => $this->reasons];
    }
}
