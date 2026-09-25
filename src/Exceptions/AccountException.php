<?php

namespace Goldnead\Accounts\Exceptions;

use RuntimeException;

/**
 * A request the account services refuse, with a message meant for the
 * customer and the form field it belongs to. Callers in other addons (an API
 * endpoint) turn it into a 422 with `field` as the error key.
 */
class AccountException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'account')
    {
        parent::__construct($message);
    }
}
