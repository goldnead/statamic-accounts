<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Support\Settings;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\BrandContext\Settings\SettingsRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
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
                $this->assertTrue(Arr::has(config('accounts'), $field['key']), $field['key']);
                $this->assertStringNotContainsString('accounts::', $field['label'], $field['key']);
                $this->assertStringNotContainsString('—', $field['label'].$field['description']);
            }
        }

        $grace = collect(Settings::settingsGroups()[1]['fields'])->firstWhere('key', 'deletion.grace_days');
        $this->assertSame(1, $grace['min']);
    }

    #[Test]
    public function a_grace_period_below_one_day_counts_as_one(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.grace_days', 0);
        $this->freezeTime();

        $this->assertSame(1, Accounts::deletion()->graceDays());

        $request = Accounts::deletion()->request($this->makeUser());
        $this->assertSame(now()->addDay()->timestamp, $request->fresh()->due_at->timestamp);
    }

    #[Test]
    public function the_subscription_policy_is_a_setting(): void
    {
        $field = collect(Settings::settingsGroups()[1]['fields'])->firstWhere('key', 'deletion.active_subscriptions');

        $this->assertSame('select', $field['type']);
        $this->assertSame(['block', 'cancel'], array_keys($field['options']));
    }
}
