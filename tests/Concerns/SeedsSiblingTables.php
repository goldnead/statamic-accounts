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

        Schema::create('marketing_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid');
            $t->string('list_handle');
            $t->string('email');
            $t->string('email_normalized');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('status');
            $t->string('token', 64);
            $t->json('meta')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('marketing_messages', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid');
            $t->string('campaign_handle')->nullable();
            $t->unsignedBigInteger('subscription_id');
            $t->string('email');
            $t->string('status');
            $t->timestamps();
        });

        Schema::create('marketing_message_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('message_id');
            $t->string('type');
            $t->text('url')->nullable();
            $t->timestamps();
        });

        Schema::create('marketing_mail_log', function (Blueprint $t) {
            $t->id();
            $t->string('email_normalized');
            $t->string('mail_class', 20);
            $t->timestamp('sent_at');
        });

        Schema::create('lead_magnet_grants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('brand_id')->default(1);
            $t->unsignedBigInteger('resource_id');
            $t->string('email', 191);
            $t->string('token_hash', 64)->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_magnet_downloads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('grant_id');
            $t->string('ip_hash')->nullable();
            $t->timestamp('downloaded_at')->nullable();
        });

        Schema::create('courses_enrollments', function (Blueprint $t) {
            $t->id();
            $t->string('user_id', 64);
            $t->string('course_entry_id', 64);
            $t->timestamps();
        });

        Schema::create('courses_lesson_states', function (Blueprint $t) {
            $t->id();
            $t->string('user_id', 64);
            $t->string('lesson_entry_id', 64);
            $t->unsignedTinyInteger('completion_percent')->default(0);
            $t->timestamps();
        });

        Schema::create('courses_lesson_events', function (Blueprint $t) {
            $t->id();
            $t->string('user_id', 64);
            $t->string('lesson_entry_id', 64);
            $t->string('event_type', 64);
            $t->timestamps();
        });

        Schema::create('courses_team_members', function (Blueprint $t) {
            $t->id();
            $t->string('owner_id', 64);
            $t->string('product', 191);
            $t->string('email', 191);
            $t->unsignedInteger('slot');
            $t->timestamps();
        });

        Schema::create('certificates_issued', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32);
            $t->string('subject_type', 64);
            $t->string('subject_id', 64);
            $t->string('course_id', 64);
            $t->string('learner_name');
            $t->timestamps();
        });

        Schema::create('client_rooms', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->string('name')->nullable();
            $t->string('owner_user_id', 64)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('client_room_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('client_rooms')->cascadeOnDelete();
            $t->string('title');
            $t->text('summary')->nullable();
            $t->timestamps();
        });

        Schema::create('client_room_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('client_rooms')->cascadeOnDelete();
            $t->string('title');
            $t->timestamps();
        });

        Schema::create('client_room_task_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('client_room_tasks')->cascadeOnDelete();
            $t->text('body')->nullable();
            $t->string('submitted_by', 64)->nullable();
            $t->timestamps();
        });

        Schema::create('client_room_task_submission_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained('client_room_task_submissions')->cascadeOnDelete();
            $t->string('container');
            $t->string('path');
            $t->timestamps();
        });

        Schema::create('client_room_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('client_rooms')->cascadeOnDelete();
            $t->string('container');
            $t->string('path');
            $t->string('uploaded_by', 64)->nullable();
            $t->timestamps();
        });

        Schema::create('inbox_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('subject');
            $t->string('counterpart_email');
            $t->string('status', 32)->default('open');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inbox_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $t->string('direction', 8);
            $t->string('from_email');
            $t->string('from_name')->nullable();
            $t->text('to')->nullable();
            $t->text('cc')->nullable();
            $t->string('subject');
            $t->text('text')->nullable();
            $t->text('html_sanitized')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('inbox_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('inbox_messages')->cascadeOnDelete();
            $t->string('filename');
            $t->string('mime')->default('application/octet-stream');
            $t->unsignedBigInteger('size')->default(0);
            $t->string('path');
            $t->timestamps();
        });

        Schema::create('offer_seat_pools', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('payment_id');
            $t->string('offer');
            $t->string('owner_email');
            $t->string('owner_name')->nullable();
            $t->unsignedInteger('seats');
            $t->string('manage_token', 64);
            $t->timestamps();
        });

        Schema::create('offer_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pool_id')->constrained('offer_seat_pools')->cascadeOnDelete();
            $t->string('email');
            $t->string('name')->nullable();
            $t->string('token', 64);
            $t->string('status', 16);
            $t->timestamps();
        });

        Schema::create('funnel_visits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('funnel_id');
            $t->string('token', 64);
            $t->string('email')->nullable();
            $t->string('name')->nullable();
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('funnel_step_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained('funnel_visits')->cascadeOnDelete();
            $t->string('node_key');
            $t->string('event');
            $t->text('payload')->nullable();
            $t->timestamps();
        });

        Schema::create('funnel_mail_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained('funnel_visits')->cascadeOnDelete();
            $t->unsignedBigInteger('funnel_id');
            $t->string('node_key');
            $t->string('to')->nullable();
            $t->timestamps();
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

        $subscription = DB::table('marketing_subscriptions')->insertGetId(['uuid' => '00000000-0000-0000-0000-00000000f000', 'list_handle' => 'newsletter', 'email' => 'fremd@example.com', 'email_normalized' => 'fremd@example.com', 'status' => 'confirmed', 'token' => str_repeat('f', 64), 'created_at' => $now]);
        $message = DB::table('marketing_messages')->insertGetId(['uuid' => '00000000-0000-0000-0000-00000000f001', 'campaign_handle' => 'herbst', 'subscription_id' => $subscription, 'email' => 'fremd@example.com', 'status' => 'sent', 'created_at' => $now]);
        DB::table('marketing_message_events')->insert(['message_id' => $message, 'type' => 'open', 'created_at' => $now]);
        DB::table('marketing_mail_log')->insert(['email_normalized' => 'fremd@example.com', 'mail_class' => 'marketing', 'sent_at' => $now]);
        $grant = DB::table('lead_magnet_grants')->insertGetId(['resource_id' => 1, 'email' => 'fremd@example.com', 'created_at' => $now]);
        DB::table('lead_magnet_downloads')->insert(['grant_id' => $grant, 'downloaded_at' => $now]);
        DB::table('courses_enrollments')->insert(['user_id' => 'fremd-id', 'course_entry_id' => 'kurs-1', 'created_at' => $now]);
        DB::table('courses_lesson_states')->insert(['user_id' => 'fremd-id', 'lesson_entry_id' => 'lektion-1', 'completion_percent' => 50, 'created_at' => $now]);
        DB::table('courses_lesson_events')->insert(['user_id' => 'fremd-id', 'lesson_entry_id' => 'lektion-1', 'event_type' => 'started', 'created_at' => $now]);
        DB::table('courses_team_members')->insert(['owner_id' => 'fremd-id', 'product' => 'kurs-team', 'email' => 'kollege@example.com', 'slot' => 1, 'created_at' => $now]);

        DB::table('certificates_issued')->insert(['code' => 'FREMD-0001', 'subject_type' => 'user', 'subject_id' => 'fremd-id', 'course_id' => 'kurs-1', 'learner_name' => 'Fremde Person', 'created_at' => $now]);
        $room = DB::table('client_rooms')->insertGetId(['email' => 'fremd@example.com', 'owner_user_id' => 'fremd-id', 'created_at' => $now]);
        DB::table('client_room_sessions')->insert(['room_id' => $room, 'title' => 'Erste Stunde', 'created_at' => $now]);
        $conversation = DB::table('inbox_conversations')->insertGetId(['subject' => 'Frage', 'counterpart_email' => 'fremd@example.com', 'created_at' => $now]);
        DB::table('inbox_messages')->insert(['conversation_id' => $conversation, 'direction' => 'in', 'from_email' => 'fremd@example.com', 'subject' => 'Frage', 'created_at' => $now]);
        $pool = DB::table('offer_seat_pools')->insertGetId(['payment_id' => 99, 'offer' => 'chor-paket', 'owner_email' => 'fremd@example.com', 'seats' => 3, 'manage_token' => str_repeat('p', 64), 'created_at' => $now]);
        DB::table('offer_seats')->insert(['pool_id' => $pool, 'email' => 'kollege@example.com', 'token' => str_repeat('q', 64), 'status' => 'claimed', 'created_at' => $now]);
        $visit = DB::table('funnel_visits')->insertGetId(['funnel_id' => 1, 'token' => str_repeat('v', 64), 'email' => 'fremd@example.com', 'created_at' => $now]);
        DB::table('funnel_step_events')->insert(['visit_id' => $visit, 'node_key' => 'start', 'event' => 'entered', 'created_at' => $now]);
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
                    // Quoted: funnels has a column called `to`.
                    $wrapped = $q->getGrammar()->wrap($column);
                    $q->orWhereRaw('lower(cast('.$wrapped.' as text)) like ?', ['%'.mb_strtolower($email).'%']);
                    $q->orWhere($column, $id);
                    $q->orWhereRaw('cast('.$wrapped.' as text) like ?', ['%"'.$id.'"%']);
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

        // Newsletter: the address as typed, a sent campaign with an open.
        $subscription = DB::table('marketing_subscriptions')->insertGetId(['uuid' => '00000000-0000-0000-0000-000000000a01', 'list_handle' => 'newsletter', 'email' => 'Sina@Example.com', 'email_normalized' => 'sina@example.com', 'first_name' => 'Sina', 'last_name' => 'Sänger', 'status' => 'confirmed', 'token' => str_repeat('a', 64), 'meta' => '{"source":"footer"}', 'confirmed_at' => $now, 'created_at' => $now]);
        $message = DB::table('marketing_messages')->insertGetId(['uuid' => '00000000-0000-0000-0000-000000000a02', 'campaign_handle' => 'herbst', 'subscription_id' => $subscription, 'email' => 'sina@example.com', 'status' => 'sent', 'created_at' => $now]);
        DB::table('marketing_message_events')->insert(['message_id' => $message, 'type' => 'click', 'url' => 'https://example.com/kurs', 'created_at' => $now]);
        DB::table('marketing_mail_log')->insert(['email_normalized' => 'sina@example.com', 'mail_class' => 'marketing', 'sent_at' => $now]);

        // A free download against her address, downloaded once.
        $grant = DB::table('lead_magnet_grants')->insertGetId(['resource_id' => 1, 'email' => 'Sina@example.com', 'token_hash' => str_repeat('b', 64), 'created_at' => $now]);
        DB::table('lead_magnet_downloads')->insert(['grant_id' => $grant, 'ip_hash' => 'def456', 'downloaded_at' => $now]);

        // Course progress under her id, a team seat she owns and one she holds.
        DB::table('courses_enrollments')->insert(['user_id' => (string) $user->id(), 'course_entry_id' => 'kurs-1', 'created_at' => $now]);
        DB::table('courses_lesson_states')->insert(['user_id' => (string) $user->id(), 'lesson_entry_id' => 'lektion-1', 'completion_percent' => 100, 'created_at' => $now]);
        DB::table('courses_lesson_events')->insert(['user_id' => (string) $user->id(), 'lesson_entry_id' => 'lektion-1', 'event_type' => 'completed', 'created_at' => $now]);
        DB::table('courses_team_members')->insert([
            ['owner_id' => (string) $user->id(), 'product' => 'kurs-team', 'email' => 'chorkollegin@example.com', 'slot' => 1, 'created_at' => $now],
            ['owner_id' => 'fremd-id', 'product' => 'kurs-team', 'email' => 'sina@example.com', 'slot' => 2, 'created_at' => $now],
        ]);

        // A certificate with her name on it.
        DB::table('certificates_issued')->insert(['code' => 'SINA-0001', 'subject_type' => 'user', 'subject_id' => (string) $user->id(), 'course_id' => 'kurs-1', 'learner_name' => 'Sina Sänger', 'created_at' => $now]);

        // A coaching room with a sitting, a task she answered with a file, and a document.
        $room = DB::table('client_rooms')->insertGetId(['email' => 'Sina@Example.com', 'name' => 'Sina Sänger', 'owner_user_id' => (string) $user->id(), 'notes' => 'Höhe eng', 'created_at' => $now]);
        DB::table('client_room_sessions')->insert(['room_id' => $room, 'title' => 'Stunde 1', 'summary' => 'Atem', 'created_at' => $now]);
        $task = DB::table('client_room_tasks')->insertGetId(['room_id' => $room, 'title' => 'Aufnahme schicken', 'created_at' => $now]);
        $submission = DB::table('client_room_task_submissions')->insertGetId(['task_id' => $task, 'body' => 'Hier meine Aufnahme', 'submitted_by' => (string) $user->id(), 'created_at' => $now]);
        DB::table('client_room_task_submission_files')->insert(['submission_id' => $submission, 'container' => 'clientrooms', 'path' => 'sina/aufnahme.m4a', 'created_at' => $now]);
        DB::table('client_room_files')->insert(['room_id' => $room, 'container' => 'clientrooms', 'path' => 'sina/plan.pdf', 'uploaded_by' => (string) $user->id(), 'created_at' => $now]);

        // A conversation with her in the inbox, one mail with an attachment.
        $conversation = DB::table('inbox_conversations')->insertGetId(['subject' => 'Probe am Freitag', 'counterpart_email' => 'Sina@example.com', 'created_at' => $now]);
        $mail = DB::table('inbox_messages')->insertGetId(['conversation_id' => $conversation, 'direction' => 'in', 'from_email' => 'sina@example.com', 'from_name' => 'Sina Sänger', 'to' => '["info@example.com"]', 'subject' => 'Probe am Freitag', 'text' => 'Bis Freitag', 'created_at' => $now]);
        DB::table('inbox_attachments')->insert(['message_id' => $mail, 'filename' => 'noten.pdf', 'size' => 10, 'path' => 'inbox/noten.pdf', 'created_at' => $now]);

        // A seat she holds in someone else's pool, a pool she bought for
        // herself alone, and one she bought with a colleague in it.
        $fremderPool = DB::table('offer_seat_pools')->insertGetId(['payment_id' => 98, 'offer' => 'chor-paket', 'owner_email' => 'chorleiterin@example.com', 'seats' => 5, 'manage_token' => str_repeat('m', 64), 'created_at' => $now]);
        DB::table('offer_seats')->insert(['pool_id' => $fremderPool, 'email' => 'sina@example.com', 'name' => 'Sina Sänger', 'token' => str_repeat('s', 64), 'status' => 'claimed', 'created_at' => $now]);
        DB::table('offer_seat_pools')->insert(['payment_id' => $paymentId, 'offer' => 'solo', 'owner_email' => 'Sina@example.com', 'owner_name' => 'Sina Sänger', 'seats' => 1, 'manage_token' => str_repeat('n', 64), 'created_at' => $now]);
        $geteilterPool = DB::table('offer_seat_pools')->insertGetId(['payment_id' => $paymentId, 'offer' => 'duo', 'owner_email' => 'sina@example.com', 'owner_name' => 'Sina Sänger', 'seats' => 2, 'manage_token' => str_repeat('o', 64), 'created_at' => $now]);
        DB::table('offer_seats')->insert(['pool_id' => $geteilterPool, 'email' => 'chorkollegin@example.com', 'token' => str_repeat('t', 64), 'status' => 'invited', 'created_at' => $now]);

        // A funnel visit with a step and a mail.
        $visit = DB::table('funnel_visits')->insertGetId(['funnel_id' => 1, 'token' => str_repeat('w', 64), 'email' => 'Sina@example.com', 'name' => 'Sina Sänger', 'payment_id' => $paymentId, 'created_at' => $now]);
        DB::table('funnel_step_events')->insert(['visit_id' => $visit, 'node_key' => 'start', 'event' => 'entered', 'created_at' => $now]);
        DB::table('funnel_mail_deliveries')->insert(['visit_id' => $visit, 'funnel_id' => 1, 'node_key' => 'danke', 'to' => 'sina@example.com', 'created_at' => $now]);
    }
}
