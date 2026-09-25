<?php

namespace Goldnead\Accounts\Actions;

use Statamic\Actions\Action;
use Statamic\Auth\User as UserContract;

/**
 * "Customer overview" in the row menu of core's Users listing, so the
 * overview is one click from where people already look for a user.
 */
class OpenCustomerOverview extends Action
{
    protected $icon = 'users';

    protected $confirm = false;

    public static function title()
    {
        return __('accounts::messages.customer_overview');
    }

    public function visibleTo($item)
    {
        return $item instanceof UserContract;
    }

    public function visibleToBulk($items)
    {
        return false;
    }

    public function authorize($user, $item)
    {
        return $user->can('view accounts');
    }

    public function run($items, $values)
    {
        //
    }

    public function redirect($items, $values)
    {
        return cp_route('accounts.customers.show', (string) $items->first()->id());
    }
}
