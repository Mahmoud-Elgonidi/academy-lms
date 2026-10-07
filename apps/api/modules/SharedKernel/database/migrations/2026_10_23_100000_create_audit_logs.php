<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The audit log (docs/architecture/03: "audit_logs (ممنوع عليه UPDATE
 * وDELETE)"): who did what sensitive thing, for which academy, on whose
 * behalf when the platform team was signed in as someone. An academy sees
 * its own entries; entries with no academy (a platform admin signing in)
 * are the platform's alone. Nothing here is ever changed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('academy_id')->nullable()->constrained('academies')->restrictOnDelete();
            $table->string('actor_type', 12);               // user | platform | system
            $table->uuid('actor_id')->nullable();
            $table->string('actor_name', 120)->nullable();
            $table->uuid('on_behalf_of')->nullable();       // the user the platform team was signed in as
            $table->uuid('impersonation_id')->nullable();
            $table->string('action', 60);                   // "billing.gateway_saved", "academy.suspended", …
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->jsonb('data')->default('{}');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at');

            $table->index(['academy_id', 'created_at']);
            $table->index(['academy_id', 'actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
        DB::statement("alter table audit_logs add constraint audit_logs_actor check (actor_type in ('user', 'platform', 'system'))");

        // An academy reads and writes its own entries only; rows without one are invisible to it.
        DB::statement('alter table audit_logs enable row level security');
        DB::statement('alter table audit_logs force row level security');
        DB::statement('create policy tenant_isolation on audit_logs using (academy_id = app_current_tenant()) with check (academy_id = app_current_tenant())');
        DB::statement('alter table audit_logs alter column academy_id set default app_current_tenant()');

        DB::unprepared(<<<'SQL'
            create or replace function lms_audit_logs_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'audit_logs is append-only' using errcode = 'P0001';
            end $$;
            create trigger audit_logs_append_only before update or delete on audit_logs
                for each row execute function lms_audit_logs_append_only();
            SQL);
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists audit_logs_append_only on audit_logs');
        DB::statement('drop function if exists lms_audit_logs_append_only()');
        Schema::dropIfExists('audit_logs');
    }
};
