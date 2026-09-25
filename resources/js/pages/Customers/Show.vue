<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import { requireElevatedSession } from '@statamic/cms';
import {
    Header, Button, Badge, Panel, Card, Avatar, Dropdown, DropdownMenu, DropdownItem, DropdownSeparator,
    Alert, ConfirmationModal, CommandPaletteItem, Table, TableColumns, TableColumn, TableRows, TableRow, TableCell,
} from '@statamic/cms/ui';

const props = defineProps([
    'overview',   // { account, payments, subscriptions, entitlements, teams, activity }
    'urls',
    'can',
    'graceDays',
    't',
]);

const account = computed(() => props.overview.account);
const confirmImpersonate = ref(false);
const confirmDeletion = ref(false);
const busy = ref(false);

function replace(text, values) {
    return Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), text);
}

// A blocker names where to fix it (the customer portal). Shown as a link,
// built from text nodes, never as HTML.
function linkParts(text) {
    return String(text)
        .split(/(https?:\/\/[^\s)]+)/)
        .filter((part) => part !== '')
        .map((part) => (/^https?:\/\//.test(part) ? { text: part, url: part } : { text: part }));
}

function post(url, method = 'post') {
    busy.value = true;
    router[method](url, {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

function impersonate() {
    confirmImpersonate.value = false;
    requireElevatedSession().then(() => post(props.urls.impersonate));
}

function scheduleDeletion() {
    confirmDeletion.value = false;
    post(props.urls.deletion);
}

// What the person has now first (abos, access, teams), the payment history
// last: it is the longest list and would push everything else off screen.
const sections = computed(() => [
    { key: 'subscriptions', heading: props.t.panel_subscriptions, addon: 'Payments', empty: props.t.none_subscriptions },
    { key: 'entitlements', heading: props.t.panel_entitlements, addon: 'Entitlements', empty: props.t.none_entitlements },
    { key: 'teams', heading: props.t.panel_teams, addon: 'Teams', empty: props.t.none_teams },
    { key: 'payments', heading: props.t.panel_payments, addon: 'Payments', empty: props.t.none_payments },
]);

const PAYMENTS_SHOWN = 10;
const paymentsExpanded = ref(false);
const paymentRows = computed(() => {
    const rows = section('payments').rows;

    return paymentsExpanded.value ? rows : rows.slice(0, PAYMENTS_SHOWN);
});

function section(key) {
    return props.overview[key] ?? { installed: false, rows: [] };
}

const statusColor = (status) => ({
    paid: 'green', active: 'green', fulfilled: 'green',
    pending: 'amber', open: 'amber', initiated: 'amber', paused: 'amber', grace: 'amber',
    failed: 'red', canceled: 'red', cancelled: 'red', expired: 'red', revoked: 'red', refunded: 'red', chargeback: 'red',
}[status] ?? 'default');
</script>

<template>
    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <Head :title="[account.email, t.customers]" />

        <Header :title="account.name || account.email" icon="user-avatar">
            <Dropdown v-if="can.manage || can.edit || can.delete">
                <DropdownMenu>
                    <DropdownItem v-if="can.edit" :text="t.edit_user" icon="user-edit" :href="urls.edit" />
                    <DropdownItem v-if="can.manage && !account.verified" :text="t.resend" icon="mail-send-email-attachment-document" @click="post(urls.resend)" />
                    <DropdownItem v-if="can.manage && !account.verified" :text="t.mark_verified" icon="mail-check" @click="post(urls.verify)" />
                    <DropdownSeparator v-if="can.manage || can.delete" />
                    <DropdownItem v-if="can.manage && account.deletion_due" :text="t.cancel_deletion" icon="history" @click="post(urls.cancelDeletion, 'delete')" />
                    <DropdownItem v-if="can.delete && !account.deletion_due" :text="t.schedule_deletion" icon="trash" variant="destructive" @click="confirmDeletion = true" />
                </DropdownMenu>
            </Dropdown>
            <Button :href="urls.index" :text="t.back" />
            <Button v-if="can.export" :href="urls.export" target="_blank" :text="t.export" icon="download" />
            <CommandPaletteItem
                v-if="can.impersonate"
                :category="$commandPalette.category.Actions"
                :text="t.impersonate"
                icon="mask"
                :action="() => (confirmImpersonate = true)"
                prioritize
                v-slot="{ text, action }"
            >
                <Button variant="primary" :text="text" icon="mask" :disabled="busy" @click="action" />
            </CommandPaletteItem>
        </Header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2 min-w-0">
                <Panel v-for="s in sections" :key="s.key" :heading="s.heading">
                    <Card>
                        <p v-if="!section(s.key).installed" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ replace(t.not_installed, { addon: s.addon }) }}
                        </p>
                        <p v-else-if="section(s.key).rows.length === 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ s.empty }}
                        </p>

                        <Table v-else-if="s.key === 'payments'">
                            <TableColumns>
                                <TableColumn>{{ t.date }}</TableColumn>
                                <TableColumn>{{ t.product }}</TableColumn>
                                <TableColumn class="text-right">{{ t.amount }}</TableColumn>
                                <TableColumn>{{ t.status }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="row in paymentRows" :key="row.id">
                                    <TableCell class="tabular-nums whitespace-nowrap">{{ row.date }}</TableCell>
                                    <TableCell>{{ row.product }}</TableCell>
                                    <TableCell class="text-right tabular-nums whitespace-nowrap">{{ row.amount }}</TableCell>
                                    <TableCell><Badge :color="statusColor(row.status)" :text="row.status_label || row.status" pill /></TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>

                        <Table v-else-if="s.key === 'subscriptions'">
                            <TableColumns>
                                <TableColumn>{{ t.product }}</TableColumn>
                                <TableColumn class="text-right">{{ t.amount }}</TableColumn>
                                <TableColumn>{{ t.interval }}</TableColumn>
                                <TableColumn>{{ t.next_payment }}</TableColumn>
                                <TableColumn>{{ t.status }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="row in section('subscriptions').rows" :key="row.id">
                                    <TableCell>{{ row.product }}</TableCell>
                                    <TableCell class="text-right tabular-nums whitespace-nowrap">{{ row.amount }}</TableCell>
                                    <TableCell>{{ row.interval_label || row.interval }}</TableCell>
                                    <TableCell class="tabular-nums">{{ row.next_payment || '–' }}</TableCell>
                                    <TableCell><Badge :color="statusColor(row.status)" :text="row.status_label || row.status" pill /></TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>

                        <Table v-else-if="s.key === 'entitlements'">
                            <TableColumns>
                                <TableColumn>{{ t.product }}</TableColumn>
                                <TableColumn>{{ t.source }}</TableColumn>
                                <TableColumn>{{ t.held_by }}</TableColumn>
                                <TableColumn>{{ t.expires }}</TableColumn>
                                <TableColumn>{{ t.status }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="row in section('entitlements').rows" :key="row.id">
                                    <TableCell class="font-medium">{{ row.product }}</TableCell>
                                    <TableCell>{{ row.source_label || row.source }}</TableCell>
                                    <TableCell>{{ row.held_by === 'email' ? t.held_by_email : t.held_by_user }}</TableCell>
                                    <TableCell class="tabular-nums">{{ row.expires || '–' }}</TableCell>
                                    <TableCell><Badge :color="statusColor(row.status)" :text="row.status_label || row.status" pill /></TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>

                        <Table v-else-if="s.key === 'teams'">
                            <TableColumns>
                                <TableColumn>{{ t.team }}</TableColumn>
                                <TableColumn>{{ t.role }}</TableColumn>
                                <TableColumn>{{ t.joined }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="row in section('teams').rows" :key="row.team_id">
                                    <TableCell class="font-medium">
                                        {{ row.team }}
                                        <Badge v-if="row.owner" class="ms-2" :text="t.owner" pill />
                                    </TableCell>
                                    <TableCell>{{ row.role_label || row.role }}</TableCell>
                                    <TableCell class="tabular-nums">{{ row.joined_at || '–' }}</TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>

                        <div
                            v-if="s.key === 'payments' && section('payments').rows.length > PAYMENTS_SHOWN && !paymentsExpanded"
                            class="pt-3 text-center"
                        >
                            <Button size="sm" variant="ghost" :text="replace(t.show_all_payments, { count: section('payments').rows.length })" @click="paymentsExpanded = true" />
                        </div>
                    </Card>
                </Panel>
            </div>

            <div class="space-y-6 min-w-0">
                <Panel :heading="t.panel_account">
                    <Card>
                        <div class="flex items-center gap-3 mb-4">
                            <Avatar :user="{ name: account.name, initials: account.initials, avatar: account.avatar }" class="size-10" />
                            <div class="min-w-0">
                                <div class="font-medium truncate">{{ account.name || account.email }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ account.email }}</div>
                            </div>
                        </div>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                            <dt class="text-gray-600 dark:text-gray-400">{{ t.status }}</dt>
                            <dd class="flex flex-wrap gap-1">
                                <Badge v-if="account.verified" color="green" :text="t.verified" pill />
                                <Badge v-else color="amber" :text="t.unverified" pill />
                                <Badge v-if="account.deletion_due && account.deletion_state === 'blocked'" color="amber" :text="replace(t.deletion_blocked_since, { date: account.deletion_due })" pill />
                                <Badge v-else-if="account.deletion_due" color="red" :text="replace(t.deletion_due, { date: account.deletion_due })" pill />
                            </dd>
                            <template v-if="account.verified_at">
                                <dt class="text-gray-600 dark:text-gray-400">{{ t.verified_at }}</dt>
                                <dd class="tabular-nums">{{ account.verified_at }}</dd>
                            </template>
                            <dt class="text-gray-600 dark:text-gray-400">{{ t.last_login }}</dt>
                            <dd class="tabular-nums">{{ account.last_login || t.never }}</dd>
                            <template v-if="account.roles && account.roles.length">
                                <dt class="text-gray-600 dark:text-gray-400">{{ t.roles }}</dt>
                                <dd>{{ account.roles.join(', ') }}</dd>
                            </template>
                            <template v-if="account.pending_email">
                                <dt class="text-gray-600 dark:text-gray-400">{{ t.pending_email }}</dt>
                                <dd>
                                    {{ account.pending_email }}
                                    <span class="text-gray-500 dark:text-gray-400">{{ replace(t.pending_email_until, { date: account.pending_email_expires }) }}</span>
                                </dd>
                            </template>
                        </dl>

                        <Alert
                            v-if="account.blockers && account.blockers.length"
                            class="mt-4"
                            variant="warning"
                            :heading="t.blockers"
                        >
                            <ul class="list-disc ps-4 space-y-1">
                                <li v-for="(blocker, i) in account.blockers" :key="i">
                                    <template v-for="(part, j) in linkParts(blocker)" :key="j">
                                        <a v-if="part.url" :href="part.url" target="_blank" rel="noopener" class="underline break-all">{{ part.text }}</a>
                                        <template v-else>{{ part.text }}</template>
                                    </template>
                                </li>
                            </ul>
                        </Alert>
                    </Card>
                </Panel>

                <Panel :heading="t.panel_activity">
                    <Card>
                        <p v-if="!section('activity').installed" class="py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ replace(t.not_installed, { addon: 'Activity' }) }}
                        </p>
                        <p v-else-if="section('activity').rows.length === 0" class="py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ t.none_activity }}
                        </p>
                        <ul v-else class="space-y-2 text-sm">
                            <li v-for="row in section('activity').rows" :key="row.id" class="flex justify-between gap-3">
                                <span class="truncate" :title="row.type">{{ row.label || row.type }}</span>
                                <span class="tabular-nums text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ row.date }}</span>
                            </li>
                        </ul>
                    </Card>
                </Panel>
            </div>
        </div>

        <ConfirmationModal
            v-model:open="confirmImpersonate"
            :title="t.impersonate_confirm_title"
            :body-text="t.impersonate_confirm_body"
            :button-text="t.impersonate"
            @confirm="impersonate"
        />

        <ConfirmationModal
            v-model:open="confirmDeletion"
            :title="t.deletion_confirm_title"
            :body-text="replace(t.deletion_confirm_body, { days: graceDays })"
            :button-text="t.schedule_deletion"
            danger
            @confirm="scheduleDeletion"
        />
    </div>
</template>
