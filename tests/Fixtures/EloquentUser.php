<?php

namespace Goldnead\Accounts\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The shape of ChoirLive's user: an Eloquent model with integer ids and the
 * `email_verified_at` column of Laravel's default users table.
 */
class EloquentUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'preferences' => 'array',
    ];
}
