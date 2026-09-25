<?php

/*
 * Marker classes for the four data addons (and teams). The contributors only
 * ask `class_exists` on these and then read the tables, which the tests
 * create with the columns the real migrations have.
 */

namespace Goldnead\StatamicPayments\Models {
    if (! class_exists(Payment::class)) {
        class Payment {}
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
