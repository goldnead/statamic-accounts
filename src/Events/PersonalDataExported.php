<?php

namespace Goldnead\Accounts\Events;

/**
 * A copy of the account's personal data was handed out, to the customer or
 * to an admin answering a request. `sections` names what it contained.
 */
class PersonalDataExported extends AccountEvent
{
    /**
     * @param  list<string>  $sections
     */
    public function __construct(string $userId, string $email, ?string $name, public readonly array $sections, public readonly string $requestedBy = 'customer')
    {
        parent::__construct($userId, $email, $name);
    }

    public static function handle(): string
    {
        return 'accounts.data.exported';
    }

    protected function extra(): array
    {
        return ['sections' => $this->sections, 'requested_by' => $this->requestedBy];
    }
}
