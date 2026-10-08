<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A guardian's consent to a child's account (docs/architecture/01, section 2;
 * 03, "حماية بيانات الأطفال"): a student under the academy's consent age signs
 * in, joins a lesson by their own link, or hands anything in only once a
 * guardian has agreed. Each consent says who agreed, when, how (their account,
 * the link they were sent, or the office recording it from paper) and to which
 * wording; it is never deleted, and taking it back only marks it withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_settings', function (Blueprint $table) {
            $table->tenant();
            $table->unsignedSmallInteger('consent_age')->default(18);   // a guardian consents for students under this age
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        DB::statement('alter table people_settings add constraint people_settings_consent_age check (consent_age in (13, 16, 18))');
        TenantTable::enableRls('people_settings');

        Schema::create('guardian_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->uuid('guardian_id')->nullable();             // who agreed; null only when the office recorded a guardian it has no account for
            $table->string('purpose', 20)->default('account');   // account: the child's own account and lessons
            $table->unsignedSmallInteger('version');              // the wording they agreed to (people::consent)
            $table->string('via', 10);                            // family (signed in) | link (the message's link) | staff (the office, from paper or a call)
            $table->uuid('recorded_by')->nullable();              // the office: who recorded it
            $table->string('note', 300)->nullable();              // the office: what it has (a signed form, a call on a date)
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestampTz('granted_at');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->uuid('withdrawn_by')->nullable();
            $table->string('withdraw_note', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'student_id']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('guardian_id', 'users');                // a guardian with consents on record is deactivated, not deleted
        });
        DB::statement("alter table guardian_consents add constraint guardian_consents_via check (via in ('family', 'link', 'staff'))");
        DB::statement("alter table guardian_consents add constraint guardian_consents_purpose check (purpose in ('account'))");
        DB::statement("alter table guardian_consents add constraint guardian_consents_who check (guardian_id is not null or (via = 'staff' and note is not null))");
        DB::statement("alter table guardian_consents add constraint guardian_consents_staff check ((via = 'staff') = (recorded_by is not null))");
        // One consent stands for a child at a time; agreeing again after taking it back is a new row.
        DB::statement('create unique index guardian_consents_one_live on guardian_consents (academy_id, student_id, purpose) where withdrawn_at is null');
        TenantTable::enableRls('guardian_consents');
        DB::statement('revoke delete on guardian_consents from lms_app');

        // What was agreed stays as it was: the only change is withdrawing it, once.
        DB::statement(<<<'SQL'
            create or replace function lms_guardian_consents_keep() returns trigger language plpgsql as $$
            begin
                if old.withdrawn_at is not null
                    or (to_jsonb(new) - array['withdrawn_at', 'withdrawn_by', 'withdraw_note']) <> (to_jsonb(old) - array['withdrawn_at', 'withdrawn_by', 'withdraw_note'])
                    or new.withdrawn_at is null then
                    raise exception 'guardian_consents keeps what was agreed; it can only be withdrawn, once';
                end if;
                return new;
            end
            $$
        SQL);
        DB::statement('create trigger guardian_consents_keep before update on guardian_consents for each row execute function lms_guardian_consents_keep()');

        // The child's and the guardians' open pages hear it (their area opens or closes).
        DB::statement(<<<'SQL'
            create or replace function lms_guardian_consents_changed() returns trigger language plpgsql as $$
            begin
                perform lms_notify_family('consents', new.academy_id, new.student_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger guardian_consents_live after insert or update on guardian_consents for each row execute function lms_guardian_consents_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists guardian_consents_live on guardian_consents');
        DB::statement('drop function if exists lms_guardian_consents_changed()');
        DB::statement('drop trigger if exists guardian_consents_keep on guardian_consents');
        DB::statement('drop function if exists lms_guardian_consents_keep()');
        Schema::dropIfExists('guardian_consents');
        Schema::dropIfExists('people_settings');
    }
};
