<?php

namespace Goldnead\Accounts\Tests\Fixtures;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;

/**
 * ChoirLive's user exactly: Eloquent, `MustVerifyEmail`, Laravel's
 * `VerifyEmail` notification.
 */
class VerifyingEloquentUser extends EloquentUser implements MustVerifyEmail
{
    use MustVerifyEmailTrait;
    use Notifiable;
}
