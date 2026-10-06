<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Notifications (docs/architecture/01-features.md, section 15): an inbox row
 * per person, a delivery per outside channel (email, WhatsApp, push), the
 * academy's own wording of each template, its channel switches, and the
 * devices push goes to. Who wants what is a per-user preference (Identity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(false);   // on once the academy's WhatsApp number is approved
            $table->boolean('push_enabled')->default(true);
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('communication_settings');

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('user_id');
            $table->string('template_key', 80);
            $table->string('category', 20);              // lessons | learning | billing | staff
            $table->string('title', 200);
            $table->text('body');
            $table->jsonb('action')->default('{}');      // where the app opens: {"screen": "report", "id": "…"}
            $table->string('dedupe_key', 160)->nullable();
            $table->boolean('urgent')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at');

            $table->tenantKey();
            $table->index(['academy_id', 'user_id', 'created_at']);
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
        });
        DB::statement('create unique index notifications_once on notifications (academy_id, user_id, dedupe_key) where dedupe_key is not null');
        DB::statement('create index notifications_unread on notifications (academy_id, user_id) where read_at is null');
        TenantTable::enableRls('notifications');

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('notification_id');
            $table->string('channel', 10);               // email | whatsapp | push
            $table->string('status', 10)->default('queued'); // queued | sent | failed | skipped
            $table->string('address', 200)->nullable();  // the email or number it went to; "3 devices" for push
            $table->string('provider', 20)->nullable();  // log | smtp | meta | fcm
            $table->string('provider_message_id', 200)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('not_before')->nullable(); // held back by the person's quiet hours
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'status', 'created_at']);
            $table->index(['academy_id', 'notification_id']);
            $table->tenantForeign('notification_id', 'notifications')->cascadeOnDelete();
        });
        TenantTable::enableRls('notification_deliveries');

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->tenant();
            $table->string('template_key', 80);
            $table->string('locale', 5);
            $table->string('title', 200);
            $table->text('body');
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->primary(['academy_id', 'template_key', 'locale']);
        });
        TenantTable::enableRls('notification_templates');

        Schema::create('push_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('user_id');
            $table->string('platform', 10);              // ios | android | web
            $table->string('token', 500);
            $table->string('app_version', 30)->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'token']);
            $table->index(['academy_id', 'user_id']);
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
        });
        TenantTable::enableRls('push_devices');
    }

    public function down(): void
    {
        foreach (['push_devices', 'notification_templates', 'notification_deliveries', 'notifications', 'communication_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
