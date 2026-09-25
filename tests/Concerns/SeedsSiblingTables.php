<?php

namespace Goldnead\Accounts\Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\Contracts\Auth\User;

/**
 * The siblings' tables, reduced to the columns this addon reads, plus one
 * customer's rows and one stranger's rows in each.
 */
trait SeedsSiblingTables
{
    protected function createSiblingTables(): void
    {
        require_once __DIR__.'/../Fakes/data-siblings.php';

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('provider');
            $t->string('product');
            $t->unsignedInteger('amount_cent');
            $t->string('currency', 3);
            $t->string('status');
            $t->string('email')->nullable();
            $t->string('name')->nullable();
            $t->string('ip_hash')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payment_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('payment_id');
            $t->string('label');
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('provider');
            $t->string('product');
            $t->unsignedInteger('amount_cent');
            $t->string('currency', 3);
            $t->string('interval');
            $t->string('status');
            $t->string('email')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('next_payment_at')->nullable();
            $t->timestamps();
        });

        Schema::create('entitlements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('brand_id')->default(1);
            $t->string('subject_type');
            $t->string('subject_id');
            $t->string('product_slug');
            $t->string('source');
            $t->string('status');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('leadhub_contacts', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->string('email_normalized');
            $t->string('user_id')->nullable();
            $t->string('full_name')->nullable();
            $t->timestamps();
        });

        Schema::create('leadhub_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contact_id');
            $t->string('type');
            $t->json('payload')->nullable();
            $t->timestamps();
        });

        Schema::create('notification_items', function (Blueprint $t) {
            $t->id();
            $t->string('user_id')->nullable();
            $t->string('email')->nullable();
            $t->string('message');
            $t->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->string('user_id')->nullable();
            $t->string('type');
            $t->string('channel');
            $t->boolean('enabled');
        });

        Schema::create('teams', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type')->default('team');
            $t->string('owner_id')->nullable();
            $t->timestamps();
        });

        Schema::create('team_members', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('team_id');
            $t->string('user_id');
            $t->string('role');
            $t->timestamp('joined_at')->nullable();
            $t->timestamps();
        });

        Schema::create('team_invitations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('team_id');
            $t->string('email');
            $t->string('role');
            $t->string('invited_by')->nullable();
            $t->timestamps();
        });

        Schema::create('team_roles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('team_id');
            $t->string('handle');
            $t->string('label');
        });

        Schema::create('leadhub_notes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('contact_id');
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('notification_digest_runs', function (Blueprint $t) {
            $t->id();
            $t->string('user_id')->nullable();
            $t->string('email')->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number');
            $t->string('buyer_name')->nullable();
            $t->string('buyer_email')->nullable();
            $t->timestamps();
        });

        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('brand_id')->default(1);
            $t->string('event_type');
            $t->string('actor_id')->nullable();
            $t->string('user_id')->nullable();
            $t->uuid('contact_uuid')->nullable();
            $t->json('properties')->nullable();
            $t->json('context')->nullable();
            $t->boolean('anonymized')->default(false);
            $t->timestamp('occurred_at')->nullable();
        });
    }

    /**
     * A stranger with rows in every table, who must come out of an erasure
     * untouched.
     */
    protected function seedStranger(): void
    {
        $now = now();

        DB::table('subscriptions')->insert(['provider' => 'mollie', 'product' => 'x', 'amount_cent' => 100, 'currency' => 'EUR', 'interval' => '1 month', 'status' => 'active', 'email' => 'fremd@example.com', 'created_at' => $now]);
        DB::table('entitlements')->insert(['subject_type' => 'email', 'subject_id' => 'fremd@example.com', 'product_slug' => 'x', 'source' => 'payment', 'status' => 'active', 'created_at' => $now]);
        DB::table('leadhub_contacts')->insert(['email' => 'fremd@example.com', 'email_normalized' => 'fremd@example.com', 'created_at' => $now]);
        DB::table('notification_items')->insert(['user_id' => 'fremd-id', 'message' => 'x', 'created_at' => $now]);
        DB::table('activities')->insert(['event_type' => 'x', 'user_id' => 'fremd-id', 'properties' => '{"email":"fremd@example.com"}', 'occurred_at' => $now]);
    }

    /**
     * Every row in every table of the test database that still names the
     * person, by id or by address. The retained tables are named by the
     * caller.
     *
     * @param  list<string>  $retained
     * @return array<string, int>
     */
    protected function rowsNaming(string $id, string $email, array $retained = []): array
    {
        $found = [];

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            if (in_array($table, array_merge($retained, ['migrations']), true)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $query = DB::table($table)->where(function ($q) use ($columns, $id, $email) {
                foreach ($columns as $column) {
                    $q->orWhereRaw('lower(cast('.$column.' as text)) like ?', ['%'.mb_strtolower($email).'%']);
                    $q->orWhere($column, $id);
                    $q->orWhereRaw('cast('.$column.' as text) like ?', ['%"'.$id.'"%']);
                }
            });

            if (($count = $query->count()) > 0) {
                $found[$table] = $count;
            }
        }

        return $found;
    }

    protected function seedSiblingRows(User $user): void
    {
        $now = now();

        $paymentId = DB::table('payments')->insertGetId(['provider' => 'mollie', 'product' => 'chorleitung-kurs', 'amount_cent' => 14900, 'currency' => 'EUR', 'status' => 'paid', 'email' => 'Sina@Example.com', 'name' => 'Sina', 'ip_hash' => 'abc123', 'meta' => '{"coupon":"HERBST"}', 'paid_at' => $now, 'created_at' => $now]);
        DB::table('payments')->insert(['provider' => 'stripe', 'product' => 'x', 'amount_cent' => 100, 'currency' => 'EUR', 'status' => 'paid', 'email' => 'fremd@example.com', 'created_at' => $now]);
        DB::table('payment_items')->insert(['payment_id' => $paymentId, 'label' => 'Kurs']);

        DB::table('subscriptions')->insert(['provider' => 'mollie', 'product' => 'chor-abo', 'amount_cent' => 900, 'currency' => 'EUR', 'interval' => '1 month', 'status' => 'active', 'email' => 'sina@example.com', 'starts_at' => $now, 'next_payment_at' => $now->copy()->addMonth(), 'created_at' => $now]);

        DB::table('entitlements')->insert([
            ['subject_type' => 'email', 'subject_id' => 'sina@example.com', 'product_slug' => 'chorleitung-kurs', 'source' => 'payment', 'status' => 'active', 'created_at' => $now],
            ['subject_type' => 'user', 'subject_id' => (string) $user->id(), 'product_slug' => 'probenraum', 'source' => 'manual', 'status' => 'active', 'created_at' => $now],
            // Same id, different kind of subject: a team, not this person.
            ['subject_type' => 'team', 'subject_id' => (string) $user->id(), 'product_slug' => 'chor-tarif', 'source' => 'manual', 'status' => 'active', 'created_at' => $now],
        ]);

        $contactId = DB::table('leadhub_contacts')->insertGetId(['email' => 'Sina@example.com', 'email_normalized' => 'sina@example.com', 'full_name' => 'Sina Sänger', 'created_at' => $now]);
        DB::table('leadhub_events')->insert(['contact_id' => $contactId, 'type' => 'purchase', 'payload' => '{"amount":149}', 'created_at' => $now]);
        DB::table('leadhub_notes')->insert(['contact_id' => $contactId, 'body' => 'Singt Alt', 'created_at' => $now]);
        DB::table('notification_digest_runs')->insert(['user_id' => (string) $user->id(), 'email' => 'sina@example.com', 'created_at' => $now]);
        DB::table('invoices')->insert(['number' => 'R-2026-001', 'buyer_name' => 'Sina Sänger', 'buyer_email' => 'sina@example.com', 'created_at' => $now]);
        DB::table('activities')->insert([
            ['event_type' => 'commerce.purchase_completed', 'user_id' => (string) $user->id(), 'properties' => '{"email":"sina@example.com"}', 'occurred_at' => $now],
            ['event_type' => 'accounts.email_verified', 'user_id' => (string) $user->id(), 'properties' => '{"user_id":"'.$user->id().'"}', 'occurred_at' => $now],
        ]);

        DB::table('notification_items')->insert(['user_id' => (string) $user->id(), 'message' => 'Neue Probe', 'created_at' => $now]);
        DB::table('notification_preferences')->insert(['user_id' => (string) $user->id(), 'type' => 'probe', 'channel' => 'mail', 'enabled' => true]);

        $teamId = DB::table('teams')->insertGetId(['name' => 'Kammerchor Nord', 'type' => 'choir', 'owner_id' => (string) $user->id(), 'created_at' => $now]);
        DB::table('team_members')->insert(['team_id' => $teamId, 'user_id' => (string) $user->id(), 'role' => 'owner', 'joined_at' => $now, 'created_at' => $now]);
    }
}
