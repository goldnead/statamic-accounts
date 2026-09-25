<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import { Header, Listing, Button, Badge, Alert, EmptyStateMenu, EmptyStateItem, DropdownItem, CommandPaletteItem } from '@statamic/cms/ui';

const props = defineProps([
    'customers',     // [{ id, email, name, verified, deletion_due, url }]
    'columns',
    'wiringUrl',
    'total',
    'setupRequired',
    't',
]);

const truncated = computed(() => props.total > props.customers.length);

function reloadPage() {
    router.reload({ preserveScroll: true });
}

function replace(text, values) {
    return Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), text);
}
</script>

<template>
    <Head :title="[t.customers]" />

    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <Header :title="t.customers" icon="users">
            <CommandPaletteItem
                :category="$commandPalette.category.Navigation"
                :text="t.wiring"
                icon="workflow"
                :url="wiringUrl"
                v-slot="{ text, url }"
            >
                <Button :text="text" icon="workflow" :href="url" />
            </CommandPaletteItem>
        </Header>

        <Alert v-if="setupRequired" variant="warning" :text="t.setup_required" class="mb-6" />

        <template v-if="customers.length === 0">
            <EmptyStateMenu :heading="t.customers_empty">
                <EmptyStateItem :href="wiringUrl" icon="workflow" :heading="t.wiring" :description="t.wiring_intro" />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                {{ truncated ? replace(t.customers_truncated, { count: customers.length, total }) : t.customers_intro }}
            </p>

            <Listing
                :items="customers"
                :columns="columns"
                sort-column="email"
                sort-direction="asc"
                preferences-prefix="accounts.customers"
                @refreshing="reloadPage"
            >
                <template #cell-email="{ row }">
                    <Link :href="row.url" class="font-medium">{{ row.email }}</Link>
                </template>

                <template #cell-name="{ row }">
                    <span class="text-gray-600 dark:text-gray-400">{{ row.name || '–' }}</span>
                </template>

                <template #cell-verified="{ row }">
                    <Badge v-if="row.verified" color="green" :text="t.verified" pill />
                    <Badge v-else color="amber" :text="t.unverified" pill />
                </template>

                <template #cell-deletion_due="{ row }">
                    <Badge v-if="row.deletion_due && row.deletion_blocked" color="amber" :text="replace(t.deletion_blocked_short, { date: row.deletion_due })" pill />
                    <Badge v-else-if="row.deletion_due" color="red" :text="row.deletion_due" pill />
                    <span v-else class="text-gray-400 dark:text-gray-600">–</span>
                </template>

                <template #prepended-row-actions="{ row }">
                    <DropdownItem :text="t.open" icon="eye" :href="row.url" />
                </template>
            </Listing>
        </template>
    </div>
</template>
