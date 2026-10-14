<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The audit log stays append-only, with one way through: erasing an account
 * (AuditTrail::forget, from Identity's account erasure) puts the erased name in
 * place of the person's and drops the address and browser they acted from.
 * What was done, when, to what and by which account never changes, nor can a
 * row be removed; the erasure says so first (lms.audit_forget, for its
 * transaction only).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function lms_audit_logs_append_only() returns trigger language plpgsql as $$
            begin
                if tg_op = 'UPDATE'
                    and coalesce(current_setting('lms.audit_forget', true), '') = 'on'
                    and (new.id, new.academy_id, new.actor_type, new.actor_id, new.on_behalf_of, new.impersonation_id,
                         new.action, new.subject_type, new.subject_id, new.created_at)
                        is not distinct from
                        (old.id, old.academy_id, old.actor_type, old.actor_id, old.on_behalf_of, old.impersonation_id,
                         old.action, old.subject_type, old.subject_id, old.created_at)
                    and (new.ip is null or new.ip is not distinct from old.ip)
                    and (new.user_agent is null or new.user_agent is not distinct from old.user_agent)
                then
                    return new;
                end if;
                raise exception 'audit_logs is append-only' using errcode = 'P0001';
            end $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function lms_audit_logs_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'audit_logs is append-only' using errcode = 'P0001';
            end $$;
            SQL);
    }
};
