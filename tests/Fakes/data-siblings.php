<?php

/*
 * Marker classes for the four data addons (and teams). The contributors only
 * ask `class_exists` on these and then read the tables, which the tests
 * create with the columns the real migrations have.
 */

namespace Goldnead\StatamicPayments\Models {
    use Illuminate\Support\Facades\DB;

    if (! class_exists(Payment::class)) {
        class Payment {}
    }

    if (! class_exists(Subscription::class)) {
        /**
         * Only what the cancel path touches: `find()` and the id.
         */
        class Subscription
        {
            public function __construct(public int $id) {}

            public static function find(int $id): ?self
            {
                return DB::table('subscriptions')->where('id', $id)->exists() ? new self($id) : null;
            }
        }
    }
}

namespace Goldnead\StatamicPayments\Support {
    use Goldnead\StatamicPayments\Models\Subscription;
    use Illuminate\Support\Facades\DB;

    if (! class_exists(Subscriptions::class)) {
        /**
         * payments' `Subscriptions::cancel(Subscription): bool`: tells the
         * provider, then writes the status.
         */
        class Subscriptions
        {
            /** @var list<int> */
            public static array $cancelled = [];

            public function cancel(Subscription $subscription): bool
            {
                self::$cancelled[] = $subscription->id;
                DB::table('subscriptions')->where('id', $subscription->id)->update(['status' => 'cancelled']);

                return true;
            }
        }
    }
}

namespace Goldnead\Invoices\Models {
    if (! class_exists(Invoice::class)) {
        class Invoice {}
    }
}

namespace Goldnead\Entitlements\Models {
    if (! class_exists(Entitlement::class)) {
        class Entitlement {}
    }
}

namespace Goldnead\Leadhub\Models {
    if (! class_exists(Contact::class)) {
        class Contact {}
    }
}

namespace Goldnead\Notifications\Models {
    if (! class_exists(NotificationItem::class)) {
        class NotificationItem {}
    }
}

namespace Goldnead\Teams\Models {
    if (! class_exists(Team::class)) {
        class Team {}
    }
}
