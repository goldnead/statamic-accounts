<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pending changes to an account: a new address waiting for its link, a
 * deletion waiting for its grace period. Kept apart from the user record on
 * purpose: an Eloquent user can only take keys that are columns, and this
 * addon must not ask a site to add columns to its users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_requests', function (Blueprint $table) {
            $table->id();
            // Statamic user ids are uuids for file users and integers for
            // Eloquent ones. Stored as a string for both.
            $table->string('user_id', 64)->index();
            $table->string('type', 32);
            $table->string('email')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');
    }
};
