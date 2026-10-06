<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The live classroom: the academy's provider, a room per lesson, each
 * teacher's outside meeting link (the fallback), and every webhook the
 * provider sent, kept once (docs/architecture/03, section 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_settings', function (Blueprint $table) {
            $table->tenant();
            $table->string('provider', 10)->default('livekit');  // livekit | external
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('classroom_settings');

        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->string('provider', 10);
            $table->string('name', 120);               // the provider's room: "{academy}_{session}"
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'session_id']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
        });
        TenantTable::enableRls('rooms');

        Schema::create('meeting_links', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('teacher_id');
            $table->string('url', 500);
            $table->timestampsTz();

            $table->primary(['academy_id', 'teacher_id']);
            $table->tenantForeign('teacher_id', 'users')->cascadeOnDelete();
        });
        TenantTable::enableRls('meeting_links');

        Schema::create('provider_webhook_inbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('provider', 10);
            $table->string('event_id', 120);
            $table->string('event', 40);
            $table->jsonb('payload');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->string('error', 500)->nullable();

            $table->tenantKey();
            $table->unique(['academy_id', 'provider', 'event_id']);
            $table->index(['academy_id', 'received_at']);
        });
        TenantTable::enableRls('provider_webhook_inbox');
    }

    public function down(): void
    {
        foreach (['provider_webhook_inbox', 'meeting_links', 'rooms', 'classroom_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
