<?php

namespace Goldnead\Accounts\Integrations\EmailTemplates;

use Goldnead\Accounts\Support\MailTemplates;
use Goldnead\EmailTemplates\Contracts\EmailTemplateSource;
use Goldnead\EmailTemplates\Support\EmailTemplateData;

/**
 * Hands this addon's default mails to `php please email-templates:import`,
 * which writes them as editable entries into the Control Panel.
 *
 * Only loaded when goldnead/statamic-email-templates is installed: the
 * provider tags it behind an `interface_exists` guard.
 */
class DefaultTemplateSource implements EmailTemplateSource
{
    public function label(): string
    {
        return 'Accounts';
    }

    public function all(): array
    {
        // A version of email-templates with the registry imports the
        // registered defaults itself; offering them here too would import
        // them twice. This source is for older versions only.
        if (app()->bound(RegistersTemplates::BINDING)) {
            return [];
        }

        $templates = [];

        foreach (MailTemplates::keys() as $key) {
            $default = MailTemplates::defaultFor($key);

            if ($default['slug'] === '') {
                continue;
            }

            $templates[] = EmailTemplateData::fromArray($default + ['source' => 'statamic-accounts']);
        }

        return $templates;
    }
}
