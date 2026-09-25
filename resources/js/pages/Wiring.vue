<script setup>
import { computed } from 'vue';
import { Head } from '@statamic/cms/inertia';
import {
    Header, Button, Badge, Panel, Card, Description,
    Table, TableColumns, TableColumn, TableRows, TableRow, TableCell,
} from '@statamic/cms/ui';

const props = defineProps([
    'events',        // [{ handle, label, description, fields, template: {slug, custom}|null, automations, webhooks }]
    'integrations',  // { email_templates, automations, webhook_manager, activity, identity, brand_context }
    'contributors',  // [{ key, label, available }]
    'indexUrl',
    't',
]);

const addons = computed(() => [
    { key: 'email_templates', name: props.t.integration_email_templates, help: props.t.integration_email_templates_help },
    { key: 'automations', name: props.t.integration_automations, help: props.t.integration_automations_help },
    { key: 'webhook_manager', name: props.t.integration_webhook_manager, help: props.t.integration_webhook_manager_help },
    { key: 'activity', name: props.t.integration_activity, help: props.t.integration_activity_help },
    { key: 'identity', name: props.t.integration_identity, help: props.t.integration_identity_help },
    { key: 'brand_context', name: props.t.integration_brand_context, help: props.t.integration_brand_context_help },
].map((addon) => ({ ...addon, ...props.integrations[addon.key] })));
</script>

<template>
    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <Head :title="[t.wiring, t.customers]" />

        <Header :title="t.wiring" icon="workflow">
            <Button :href="indexUrl" :text="t.back" />
            <Button
                v-if="integrations.webhook_manager.catalogue_url"
                :href="integrations.webhook_manager.catalogue_url"
                :text="t.trigger_catalogue"
                icon="link"
            />
        </Header>

        <Description class="mb-6" :text="t.wiring_intro" />

        <Panel :heading="t.events">
            <Card>
                <Table>
                    <TableColumns>
                        <TableColumn>{{ t.event }}</TableColumn>
                        <TableColumn>{{ t.handle }}</TableColumn>
                        <TableColumn>{{ t.mail_template }}</TableColumn>
                        <TableColumn class="text-right">{{ t.automations }}</TableColumn>
                        <TableColumn class="text-right">{{ t.webhooks }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="event in events" :key="event.handle">
                            <TableCell class="align-top">
                                <div class="font-medium">{{ event.label }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 max-w-md">{{ event.description }}</div>
                            </TableCell>
                            <TableCell class="align-top"><code class="text-xs">{{ event.handle }}</code></TableCell>
                            <TableCell class="align-top">
                                <template v-if="event.template">
                                    <code class="text-xs">{{ event.template.slug }}</code>
                                    <div>
                                        <Badge v-if="event.template.custom" color="green" :text="t.template_custom" pill />
                                        <Badge v-else :text="t.template_default" pill />
                                    </div>
                                </template>
                                <span v-else class="text-gray-400 dark:text-gray-600">–</span>
                            </TableCell>
                            <TableCell class="align-top text-right tabular-nums">
                                <span v-if="event.automations === null" class="text-xs text-gray-500 dark:text-gray-400">{{ t.not_installed_short }}</span>
                                <span v-else>{{ event.automations }}</span>
                            </TableCell>
                            <TableCell class="align-top text-right tabular-nums">
                                <span v-if="event.webhooks === null" class="text-xs text-gray-500 dark:text-gray-400">{{ t.not_installed_short }}</span>
                                <span v-else>{{ event.webhooks }}</span>
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </Card>
        </Panel>

        <div class="grid gap-6 lg:grid-cols-2">
            <Panel :heading="t.integrations">
                <Card>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                        <li v-for="addon in addons" :key="addon.key" class="py-3 first:pt-0 last:pb-0 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-medium flex items-center gap-2">
                                    {{ addon.name }}
                                    <Badge v-if="addon.installed" color="green" :text="t.installed" pill />
                                    <Badge v-else :text="t.missing" pill />
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ addon.help }}</div>
                            </div>
                            <Button v-if="addon.installed && addon.url" size="sm" :href="addon.url" :text="t.open_addon" />
                        </li>
                    </ul>
                </Card>
            </Panel>

            <Panel :heading="t.contributors" :subheading="t.contributors_intro">
                <Card>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                        <li v-for="contributor in contributors" :key="contributor.key" class="py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-4">
                            <div>
                                <span class="font-medium">{{ contributor.label }}</span>
                                <code class="ms-2 text-xs text-gray-600 dark:text-gray-400">{{ contributor.key }}.json</code>
                            </div>
                            <Badge v-if="contributor.available" color="green" :text="t.available" pill />
                            <Badge v-else :text="t.unavailable" pill />
                        </li>
                    </ul>
                </Card>
            </Panel>
        </div>
    </div>
</template>
