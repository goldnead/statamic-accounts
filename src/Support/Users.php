<?php

namespace Goldnead\Accounts\Support;

use Statamic\Auth\User;
use Statamic\Facades\User as UserFacade;

/**
 * Statamic's user lookups, narrowed to the class that has `id()`, `name()`
 * and `can()`. Both repositories (file and Eloquent) return a subclass of
 * `Statamic\Auth\User`; the contract they are typed against declares none
 * of those methods.
 */
class Users
{
    public static function find(string|int|null $id): ?User
    {
        if ($id === null || $id === '') {
            return null;
        }

        $user = UserFacade::find($id);

        return $user instanceof User ? $user : null;
    }

    public static function findByEmail(string $email): ?User
    {
        $user = UserFacade::findByEmail($email);

        return $user instanceof User ? $user : null;
    }

    public static function current(): ?User
    {
        $user = UserFacade::current();

        return $user instanceof User ? $user : null;
    }
}
