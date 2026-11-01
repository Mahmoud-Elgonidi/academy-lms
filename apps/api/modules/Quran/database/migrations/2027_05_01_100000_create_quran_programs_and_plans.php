<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The Quran program and each course's plan on it (docs/architecture/02,
 * section 26):
 *
 * - quran_programs: the academy's programs (its own curriculum of the
 *   Quran): a track, a scope (surah:verse to surah:verse; lessons on the
 *   Noorani Qaida), forward or backward, the unit and the new amount a
 *   lesson, the near and far reviews, the grade a new portion needs, the exam
 *   stops, and whether it leads to a certificate. Saved only through
 *   SaveQuranProgram; its shape stays once a plan is on it.
 * - quran_plans: a course's plan (or a group's, shared by its members), one
 *   live at a time: its program, where the learner is (the verse their next
 *   new portion starts at; a lesson's number on the Noorani Qaida), their
 *   own pace, and where the far review reached. Made only through
 *   StartQuranPlan, changed through AdjustQuranPlan and RecordQuranLesson.
 * - quran_lesson_results: what a lesson recorded for each student (each
 *   part's verses, mistakes, worked-out and given grade, the teacher's note),
 *   and whether the position moved; one per lesson and student, never
 *   changed (RecordQuranLesson).
 * - quran_settings: riwaya and mushaf, default amounts, the mistake types
 *   and their weights, the grade scale and the memorization threshold, the
 *   automatic recitation check, exams and certificates.
 * - recitation_mistakes.kind takes the academy's own mistake types too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name', 120);
            $table->string('track', 10);                            // hifz | tilawa | talqin | noorani | review | ijaza
            $table->string('preset', 30)->nullable();               // the ready track it started from
            $table->unsignedSmallInteger('scope_from_surah')->nullable();   // null on the Noorani Qaida
            $table->unsignedSmallInteger('scope_from_verse');       // a lesson's number on the Noorani Qaida
            $table->unsignedSmallInteger('scope_to_surah')->nullable();
            $table->unsignedSmallInteger('scope_to_verse');
            $table->string('direction', 10);                        // forward | backward
            $table->string('unit', 15);                             // verses | lines | page | quarter_hizb | lesson
            $table->decimal('new_amount', 5, 2);
            $table->unsignedSmallInteger('near_portions')->default(0);
            $table->unsignedSmallInteger('far_pages')->default(0);
            $table->string('pass_grade', 12);
            $table->string('exam_stops', 6)->default('none');       // none | juz | surah
            $table->boolean('counts_for_certificate')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->tenantKey();
            $table->index(['academy_id', 'archived_at']);
        });
        DB::statement("alter table quran_programs add constraint quran_programs_track check (track in ('hifz', 'tilawa', 'talqin', 'noorani', 'review', 'ijaza'))");
        DB::statement("alter table quran_programs add constraint quran_programs_direction check (direction in ('forward', 'backward'))");
        DB::statement("alter table quran_programs add constraint quran_programs_unit check (unit in ('verses', 'lines', 'page', 'quarter_hizb', 'lesson'))");
        DB::statement("alter table quran_programs add constraint quran_programs_pass check (pass_grade in ('excellent', 'very_good', 'good', 'acceptable'))");
        DB::statement("alter table quran_programs add constraint quran_programs_stops check (exam_stops in ('none', 'juz', 'surah'))");
        DB::statement(<<<'SQL'
            alter table quran_programs add constraint quran_programs_scope check (
                (track = 'noorani' and scope_from_surah is null and scope_to_surah is null and unit = 'lesson' and scope_to_verse >= scope_from_verse)
                or (track <> 'noorani' and scope_from_surah between 1 and 114 and scope_to_surah between 1 and 114 and unit <> 'lesson'
                    and (scope_from_surah, scope_from_verse) <= (scope_to_surah, scope_to_verse))
            )
        SQL);
        DB::statement('alter table quran_programs add constraint quran_programs_amounts check (new_amount >= 0 and near_portions <= 10 and far_pages <= 40 and scope_from_verse >= 1)');
        TenantTable::enableRls('quran_programs');

        Schema::create('quran_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('program_id');
            $table->uuid('enrollment_id')->nullable();              // a course's plan
            $table->uuid('student_id')->nullable();                 // its learner
            $table->uuid('group_id')->nullable();                   // or a group's, shared by its members
            $table->unsignedSmallInteger('position_surah')->nullable();     // null on the Noorani Qaida
            $table->unsignedSmallInteger('position_verse')->nullable();     // null once the scope is done
            $table->decimal('new_amount', 5, 2)->nullable();        // the learner's own pace; null keeps the program's
            $table->unsignedSmallInteger('near_portions')->nullable();
            $table->unsignedSmallInteger('far_pages')->nullable();
            $table->unsignedSmallInteger('far_cursor')->nullable(); // the last page the far review reached
            $table->string('status', 10)->default('active');        // active | closed (another plan took its place)
            $table->timestampTz('finished_at')->nullable();         // the scope is done
            $table->timestampTz('closed_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->tenantKey();
            $table->index(['academy_id', 'student_id']);
            $table->tenantForeign('program_id', 'quran_programs');
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('group_id', 'lesson_groups')->cascadeOnDelete();
        });
        DB::statement("alter table quran_plans add constraint quran_plans_status check (status in ('active', 'closed'))");
        DB::statement('alter table quran_plans add constraint quran_plans_owner check ((enrollment_id is null) <> (group_id is null) and (enrollment_id is null) = (student_id is null))');
        DB::statement('alter table quran_plans add constraint quran_plans_pace check ((new_amount is null or new_amount >= 0) and (near_portions is null or near_portions <= 10) and (far_pages is null or far_pages <= 40))');
        DB::statement("create unique index quran_plans_one_live_course on quran_plans (academy_id, enrollment_id) where status = 'active' and enrollment_id is not null");
        DB::statement("create unique index quran_plans_one_live_group on quran_plans (academy_id, group_id) where status = 'active' and group_id is not null");
        TenantTable::enableRls('quran_plans');

        Schema::create('quran_lesson_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('student_id');
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('plan_id');
            $table->uuid('recorded_by');
            $table->jsonb('parts');                                 // {new|near|far: {ranges, pages, mistakes, computed, grade, penalty, note}}
            $table->string('new_grade', 12)->nullable();
            $table->boolean('advanced')->default(false);            // the position moved past the new part
            $table->boolean('frozen')->default(false);              // the course was paused or over: nothing moved
            $table->jsonb('position_before')->nullable();
            $table->jsonb('position_after')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampTz('created_at');
            $table->tenantKey();
            $table->unique(['academy_id', 'session_id', 'student_id']);
            $table->index(['academy_id', 'plan_id', 'recorded_at']);
            $table->index(['academy_id', 'student_id', 'recorded_at']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('plan_id', 'quran_plans')->cascadeOnDelete();
            $table->tenantForeign('recorded_by', 'users');
        });
        TenantTable::enableRls('quran_lesson_results');

        Schema::table('quran_settings', function (Blueprint $table) {
            $table->string('riwaya', 10)->default('hafs');
            $table->string('mushaf', 20)->default('madinah_604');
            $table->string('default_unit', 15)->default('page');
            $table->decimal('default_new_amount', 5, 2)->default(0.5);
            $table->unsignedSmallInteger('default_near_portions')->default(3);
            $table->unsignedSmallInteger('default_far_pages')->default(2);
            $table->jsonb('mistake_types')->nullable();             // null: Domain\Grading\MistakeTypes::DEFAULTS
            $table->jsonb('grade_scale')->nullable();               // null: Domain\Grading\GradeScale::DEFAULT
            $table->string('memorization_threshold', 12)->default('good');
            $table->boolean('auto_recitation_check')->default(true);     // with the AI helper on: recited homework and pages are checked
            $table->boolean('exams_enabled')->default(false);
            $table->string('examiner', 15)->default('other_teacher');   // other_teacher | supervisor | any_teacher
            $table->boolean('certificates_enabled')->default(false);
            $table->string('certificate_title', 120)->nullable();
            $table->string('certificate_signature', 120)->nullable();
            $table->boolean('certificates_family')->default(true);
        });
        DB::statement("alter table quran_settings add constraint quran_settings_riwaya check (riwaya in ('hafs') and mushaf in ('madinah_604'))");
        DB::statement("alter table quran_settings add constraint quran_settings_threshold check (memorization_threshold in ('excellent', 'very_good', 'good', 'acceptable'))");
        DB::statement("alter table quran_settings add constraint quran_settings_examiner check (examiner in ('other_teacher', 'supervisor', 'any_teacher'))");
        DB::statement("alter table quran_settings add constraint quran_settings_defaults check (default_unit in ('verses', 'lines', 'page', 'quarter_hizb') and default_new_amount > 0 and default_near_portions <= 10 and default_far_pages <= 40)");

        // The academy's own mistake types (settings.mistake_types) are marked like the three it always has.
        DB::statement('alter table recitation_mistakes drop constraint recitation_mistakes_kind');
        DB::statement('alter table recitation_mistakes alter column kind type varchar(30)');
        DB::statement("alter table recitation_mistakes add constraint recitation_mistakes_kind check (kind ~ '^[a-z][a-z0-9_]{0,29}$')");
    }

    public function down(): void
    {
        DB::statement("delete from recitation_mistakes where kind not in ('wrong', 'hesitation', 'tajweed')");
        DB::statement('alter table recitation_mistakes drop constraint recitation_mistakes_kind');
        DB::statement('alter table recitation_mistakes alter column kind type varchar(12)');
        DB::statement("alter table recitation_mistakes add constraint recitation_mistakes_kind check (kind in ('wrong', 'hesitation', 'tajweed'))");
        foreach (['quran_settings_riwaya', 'quran_settings_threshold', 'quran_settings_examiner', 'quran_settings_defaults'] as $constraint) {
            DB::statement("alter table quran_settings drop constraint if exists {$constraint}");
        }
        Schema::table('quran_settings', fn (Blueprint $table) => $table->dropColumn([
            'riwaya', 'mushaf', 'default_unit', 'default_new_amount', 'default_near_portions', 'default_far_pages', 'mistake_types', 'grade_scale',
            'memorization_threshold', 'auto_recitation_check', 'exams_enabled', 'examiner', 'certificates_enabled', 'certificate_title',
            'certificate_signature', 'certificates_family',
        ]));
        Schema::dropIfExists('quran_lesson_results');
        Schema::dropIfExists('quran_plans');
        Schema::dropIfExists('quran_programs');
    }
};
