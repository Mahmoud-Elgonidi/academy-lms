<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Exams and certificates of the Quran (docs/architecture/02, section 26):
 *
 * - quran_exams: a learner's exam at a stop of their plan's program (the end
 *   of a part or a surah as the program's exam_stops say, or the whole
 *   program; a khatma when it is the whole Quran). Suggested when a lesson
 *   moves the plan past the stop (one open a plan, student and stop),
 *   booked as an assessment lesson with an examiner (Scheduling's
 *   AssessmentLessons), recorded by that examiner alone (grade from the
 *   mistakes, passed at the program's pass grade; a fail says why and when
 *   to try again, and the retake is suggested for that day), or cancelled.
 *   Only through Actions\Exams\*.
 * - quran_certificates: what a passed exam earns when the academy gives
 *   certificates and the program counts for one: a number of the academy's
 *   in order (Q-2026-000123), a random code anyone may verify by, the
 *   portion, riwaya, grade and examiner as they were at issue. Never
 *   changed after issue but to revoke it (with a reason), held by a trigger.
 *   Only through Actions\Certificates\*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('plan_id')->nullable();                    // null only once the learner's plan was erased behind a certificate
            $table->uuid('program_id');
            $table->uuid('student_id');
            $table->uuid('enrollment_id')->nullable();              // the learner's course (a group's member: their own seat)
            $table->string('stop_kind', 8);                         // juz | surah | program | khatma
            $table->unsignedSmallInteger('stop_number')->nullable(); // a part's or a surah's number; null for the whole program
            $table->string('status', 10)->default('suggested');     // suggested | booked | passed | failed | cancelled
            $table->uuid('retake_of')->nullable();                  // the failed exam this one takes again
            $table->date('not_before')->nullable();                 // a retake is not booked before its day (the academy's calendar)
            $table->uuid('examiner_id')->nullable();
            $table->uuid('session_id')->nullable();                 // the assessment lesson booked
            $table->timestampTz('booked_at')->nullable();
            $table->uuid('booked_by')->nullable();
            $table->string('grade', 12)->nullable();                // the grade that counts
            $table->string('computed', 12)->nullable();             // the grade the mistakes gave
            $table->decimal('penalty', 8, 4)->nullable();
            $table->jsonb('mistakes')->nullable();                  // kind => count
            $table->text('result_note')->nullable();                // the examiner's words to the learner (a fail always has them)
            $table->date('retake_on')->nullable();                  // a fail: when to try again
            $table->timestampTz('recorded_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->tenantKey();
            $table->index(['academy_id', 'student_id', 'status']);
            $table->index(['academy_id', 'examiner_id', 'status']);
            $table->tenantForeign('plan_id', 'quran_plans');
            $table->tenantForeign('program_id', 'quran_programs');
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('examiner_id', 'users');
            $table->tenantForeign('session_id', 'sessions');
            $table->tenantForeign('retake_of', 'quran_exams');
        });
        DB::statement("alter table quran_exams add constraint quran_exams_status check (status in ('suggested', 'booked', 'passed', 'failed', 'cancelled'))");
        DB::statement(<<<'SQL'
            alter table quran_exams add constraint quran_exams_stop check (
                (stop_kind = 'juz' and stop_number between 1 and 30)
                or (stop_kind = 'surah' and stop_number between 1 and 114)
                or (stop_kind in ('program', 'khatma') and stop_number is null)
            )
        SQL);
        DB::statement(<<<'SQL'
            alter table quran_exams add constraint quran_exams_steps check (
                (status = 'suggested' or status = 'cancelled' or (examiner_id is not null and session_id is not null))
                and (status not in ('passed', 'failed') or (grade is not null and recorded_at is not null))
                and (status <> 'failed' or (retake_on is not null and result_note is not null))
                and (status <> 'cancelled' or cancelled_at is not null)
            )
        SQL);
        DB::statement("create unique index quran_exams_one_open on quran_exams (academy_id, plan_id, student_id, stop_kind, coalesce(stop_number, 0)) where status in ('suggested', 'booked')");
        TenantTable::enableRls('quran_exams');

        Schema::create('quran_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->uuid('program_id');
            $table->unsignedInteger('seq');                         // the academy's certificates in order
            $table->string('number', 20);                           // Q-2026-000123
            $table->string('code', 32)->unique();                   // what anyone verifies it by: random, never guessed
            $table->jsonb('portion');                               // {kind, number, program, track, scope}
            $table->string('riwaya', 10);
            $table->string('grade', 12);
            $table->uuid('examiner_id')->nullable();
            $table->string('title', 120)->nullable();               // the academy's title and signature as they were at issue
            $table->string('signature', 120)->nullable();
            $table->timestampTz('issued_at');
            $table->uuid('issued_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoke_reason', 300)->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->timestampsTz();
            $table->tenantKey();
            $table->unique(['academy_id', 'seq']);
            $table->unique(['academy_id', 'number']);
            $table->unique(['academy_id', 'exam_id']);
            $table->index(['academy_id', 'student_id']);
            $table->tenantForeign('exam_id', 'quran_exams');
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('program_id', 'quran_programs');
            $table->tenantForeign('examiner_id', 'users');
        });
        DB::statement('alter table quran_certificates add constraint quran_certificates_revoked check ((revoked_at is null) = (revoke_reason is null))');
        // An issued certificate never changes: only its revocation is written, once.
        DB::statement(<<<'SQL'
            create or replace function quran_certificates_frozen() returns trigger language plpgsql as $$
            begin
                if old.revoked_at is not null
                    or (to_jsonb(new) - array['revoked_at', 'revoke_reason', 'revoked_by', 'updated_at'])
                        is distinct from (to_jsonb(old) - array['revoked_at', 'revoke_reason', 'revoked_by', 'updated_at']) then
                    raise exception 'quran_certificates: an issued certificate is only revoked, once';
                end if;
                return new;
            end
            $$
        SQL);
        DB::statement('create trigger quran_certificates_frozen before update on quran_certificates for each row execute function quran_certificates_frozen()');
        TenantTable::enableRls('quran_certificates');
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_certificates');
        DB::statement('drop function if exists quran_certificates_frozen()');
        Schema::dropIfExists('quran_exams');
    }
};
