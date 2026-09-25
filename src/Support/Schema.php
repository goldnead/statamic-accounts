<?php

namespace Goldnead\Accounts\Support;

use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Throwable;

/**
 * Whether this addon's table exists. The Control Panel asks before reading,
 * so a site that installed the addon but has not migrated yet sees a notice
 * instead of a database error.
 */
class Schema
{
    protected static ?bool $ready = null;

    public static function ready(): bool
    {
        if (static::$ready === true) {
            return true;
        }

        try {
            return static::$ready = DatabaseSchema::hasTable('account_requests');
        } catch (Throwable) {
            return false;
        }
    }

    public static function flush(): void
    {
        static::$ready = null;
    }
}
