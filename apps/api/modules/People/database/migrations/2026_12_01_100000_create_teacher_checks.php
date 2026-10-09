<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Keeping children safe (docs/architecture/02, section 15): the documents a
 * teacher's vetting rests on (a criminal record check above all, then an ID
 * and qualifications), each checked by the academy and valid until a date;
 * whether the academy asks for a valid check before a teacher teaches anyone
 * under 18; and a pause that keeps a teacher from new lessons with minors
 * while a concern is looked into.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->string('kind', 16);                         // criminal_record | identity | qualification | other
            $table->string('reference', 120)->nullable();       // the certificate's number, as written on it
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();             // set or confirmed by whoever verifies it; none: it does not run out
            $table->string('file_path')->nullable();            // the scan, on the private disk
            $table->string('file_name', 190)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 10)->default('pending');   // pending → verified | rejected
            $table->uuid('uploaded_by');                        // the teacher, or someone at the academy for them
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();   // why it was turned down (the teacher reads it), or what was checked
            $table->timestampTz('expiry_warned_at')->nullable(); // the month-before reminder went out
            $table->timestampTz('expired_noted_at')->nullable(); // the "it has run out" message went out
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'teacher_id', 'kind']);
            $table->index(['academy_id', 'status']);
            $table->tenantForeign('teacher_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('uploaded_by', 'users');
            $table->tenantForeign('decided_by', 'users');
        });
        DB::statement("alter table teacher_checks add constraint teacher_checks_kind check (kind in ('criminal_record', 'identity', 'qualification', 'other'))");
        DB::statement("alter table teacher_checks add constraint teacher_checks_status check (status in ('pending', 'verified', 'rejected'))");
        DB::statement("alter table teacher_checks add constraint teacher_checks_decided check ((status = 'pending') = (decided_at is null))");
        DB::statement("alter table teacher_checks add constraint teacher_checks_rejected check (status <> 'rejected' or decision_note is not null)");
        DB::statement('alter table teacher_checks add constraint teacher_checks_dates check (expires_on is null or issued_on is null or expires_on >= issued_on)');
        TenantTable::enableRls('teacher_checks');
        // The record of who was checked, and when, stays: a check is replaced by a newer one, never removed.
        DB::statement('revoke delete on teacher_checks from lms_app');
        // The teacher's own page and the academy's checks page hear it change.
        DB::statement(<<<'SQL'
            create or replace function lms_teacher_checks_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'checks:' || new.academy_id);
                perform pg_notify('lms_live', 'my-checks:' || new.teacher_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger teacher_checks_live after insert or update on teacher_checks for each row execute function lms_teacher_checks_changed()');

        Schema::table('teacher_profiles', function (Blueprint $table) {
            // Kept from new lessons with minors while a safeguarding concern is looked into (never told on screen why).
            $table->timestampTz('minors_paused_at')->nullable();
            $table->uuid('minors_paused_by')->nullable();
            $table->string('minors_pause_reason', 500)->nullable();
        });

        // An erased teacher's gender goes with the rest of their profile (Privacy's erasure blanks it).
        DB::statement('alter table teacher_profiles alter column gender drop not null');

        Schema::table('people_settings', function (Blueprint $table) {
            // A teacher teaches anyone under 18 only with a verified criminal record check that has not run out.
            $table->boolean('require_minor_check')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('people_settings', fn (Blueprint $table) => $table->dropColumn('require_minor_check'));
        Schema::table('teacher_profiles', fn (Blueprint $table) => $table->dropColumn(['minors_paused_at', 'minors_paused_by', 'minors_pause_reason']));
        Schema::dropIfExists('teacher_checks');
        DB::statement('drop function if exists lms_teacher_checks_changed()');
    }
};
