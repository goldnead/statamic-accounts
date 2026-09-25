<?php

namespace Goldnead\Accounts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * One fact about a customer account.
 *
 * Carries ids and the fields a receiver needs, never a user object and never
 * a token: the payload goes out as a webhook body and into an automation's
 * context, and a signed link in there would hand the account to whoever
 * reads the log of the receiving system.
 */
abstract class AccountEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly ?string $name = null,
    ) {}

    /**
     * The trigger handle in automations and webhook-manager. Public surface:
     * a stored flow or webhook refers to it by this string.
     */
    abstract public static function handle(): string;

    /**
     * Everything a receiver gets, as plain values.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_merge([
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
        ], $this->extra());
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }
}
