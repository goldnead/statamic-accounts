/**
 * Control Panel entry. The registered names must match what the controllers
 * pass to `Inertia::render()`, exactly: a mismatch is a blank screen with
 * nothing in the log.
 */

import CustomersIndex from './pages/Customers/Index.vue';
import CustomersShow from './pages/Customers/Show.vue';
import Wiring from './pages/Wiring.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('accounts::Customers/Index', CustomersIndex);
    Statamic.$inertia.register('accounts::Customers/Show', CustomersShow);
    Statamic.$inertia.register('accounts::Wiring', Wiring);
});
