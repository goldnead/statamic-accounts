<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Support\Settings;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use PHPUnit\Framework\Attributes\Test;

class SettingsTest extends TestCase
{
    #[Test]
    public function the_addon_registers_with_the_settings_layer(): void
    {
        $registry = app(SettingsRegistry::class);

        $this->assertTrue($registry->has('accounts'));
        $this->assertSame(Settings::class, $registry->provider('accounts'));
        $this->assertSame('manage accounts settings', $registry->permission('accounts'));
        $this->assertSame([], $registry->failures());
    }

    #[Test]
    public function every_field_points_at_an_existing_config_key_and_has_a_german_label(): void
    {
        app()->setLocale('de');

        foreach (Settings::settingsGroups() as $group) {
            foreach ($group['fields'] as $field) {
                $this->assertNotNull(config('accounts.'.$field['key']), $field['key']);
                $this->assertStringNotContainsString('accounts::', $field['label'], $field['key']);
                $this->assertStringNotContainsString('—', $field['label'].$field['description']);
            }
        }

        $this->assertSame('deletion.grace_days', collect(Settings::settingsGroups()[1]['fields'])->first()['key']);
    }
}
