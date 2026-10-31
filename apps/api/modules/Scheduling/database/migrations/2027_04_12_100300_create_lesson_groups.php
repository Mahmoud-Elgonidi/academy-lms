<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): a group is one
 * teacher, one subject (and level), weekly times of its own on the
 * teacher's clock, and seats. Each student in it is a course of their own
 * (an enrollment on a group plan, with their own credit) and a membership
 * from a day to a day. A group's lesson is one session with a participant
 * for every member live that day; each student's funding and outcome are
 * their own (session_participants). A weekly time belongs to a course or
 * to a group, never both; the teacher's no-overlap guard is unchanged, so a
 * group's time is the teacher's time once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->uuid('level_id')->nullable();
            $table->string('age_group', 10)->nullable();        // People's AgeGroup, from the first member when not chosen
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('min_to_start')->nullable(); // none: it starts with its first student
            $table->unsignedSmallInteger('session_minutes');
            $table->unsignedSmallInteger('sessions_per_week');
            $table->string('anchor_tz', 64);
            $table->string('status', 10)->default('forming');    // forming | running | ended
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('name', 120)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'teacher_id', 'status']);
            $table->index(['academy_id', 'subject_id', 'status']);
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('subject_id', 'subjects');
            $table->tenantForeign('level_id', 'subject_levels');
        });
        DB::statement("alter table lesson_groups add constraint lesson_groups_status check (status in ('forming', 'running', 'ended'))");
        DB::statement('alter table lesson_groups add constraint lesson_groups_capacity check (capacity between 2 and 20 and (min_to_start is null or min_to_start between 1 and capacity))');
        DB::statement('alter table lesson_groups add constraint lesson_groups_shape check (session_minutes > 0 and sessions_per_week > 0)');
        DB::statement("alter table lesson_groups add constraint lesson_groups_age_group check (age_group is null or age_group in ('kids', 'middle', 'teen', 'adults'))");
        TenantTable::enableRls('lesson_groups');

        Schema::create('group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('group_id');
            $table->uuid('enrollment_id');
            $table->uuid('student_id');
            $table->date('joined_on');                           // first day in the group, on the academy's calendar
            $table->date('left_on')->nullable();                 // first day out of it
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'group_id']);
            $table->tenantForeign('group_id', 'lesson_groups')->cascadeOnDelete();
            $table->tenantForeign('enrollment_id', 'enrollments');
            $table->tenantForeign('student_id', 'users');
        });
        DB::statement('alter table group_members add constraint group_members_days check (left_on is null or left_on >= joined_on)');
        // One live membership per course, and a student once in a group at a time.
        DB::statement('create unique index group_members_one_live_enrollment on group_members (academy_id, enrollment_id) where left_on is null');
        DB::statement('create unique index group_members_one_live_student on group_members (academy_id, group_id, student_id) where left_on is null');
        TenantTable::enableRls('group_members');

        // A weekly time belongs to a course (one student) or to a group (its members): exactly one.
        Schema::table('recurring_slots', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('enrollment_id');
            $table->index(['academy_id', 'group_id']);
            $table->tenantForeign('group_id', 'lesson_groups')->cascadeOnDelete();
        });
        DB::statement('alter table recurring_slots alter column enrollment_id drop not null');
        DB::statement('alter table recurring_slots alter column student_id drop not null');
        DB::statement('alter table recurring_slots add constraint recurring_slots_owner check ((enrollment_id is null) <> (group_id is null) and (group_id is null) = (student_id is not null))');

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('enrollment_id');  // a group's lesson; enrollment_id stays null on it
            $table->index(['academy_id', 'group_id', 'starts_at']);
            $table->tenantForeign('group_id', 'lesson_groups');
        });
        DB::statement('alter table sessions add constraint sessions_course_or_group check (enrollment_id is null or group_id is null)');

        // Each student in a lesson: their own course, funding and outcome (a group lesson's; a one-to-one lesson keeps its session's).
        Schema::table('session_participants', function (Blueprint $table) {
            $table->uuid('enrollment_id')->nullable();
            $table->string('funding_status', 20)->nullable();   // funded | unfunded | makeup
            $table->string('outcome', 10)->nullable();          // attended | absent | excused | makeup
            $table->index(['academy_id', 'enrollment_id']);
            $table->tenantForeign('enrollment_id', 'enrollments');
        });
        DB::statement("alter table session_participants add constraint session_participants_funding check (funding_status is null or funding_status in ('funded', 'unfunded', 'makeup'))");
        DB::statement("alter table session_participants add constraint session_participants_outcome check (outcome is null or outcome in ('attended', 'absent', 'excused', 'makeup'))");

        Schema::table('scheduling_settings', function (Blueprint $table) {
            $table->string('late_join', 10)->default('discount');   // discount | private | group | none
            $table->boolean('allow_mixed_ages')->default(false);
            $table->unsignedSmallInteger('group_min_default')->nullable();
        });
        DB::statement("alter table scheduling_settings add constraint scheduling_settings_late_join check (late_join in ('discount', 'private', 'group', 'none'))");
        DB::statement('alter table scheduling_settings add constraint scheduling_settings_group_min check (group_min_default is null or group_min_default between 1 and 20)');
    }

    public function down(): void
    {
        DB::statement('alter table scheduling_settings drop constraint if exists scheduling_settings_group_min');
        DB::statement('alter table scheduling_settings drop constraint if exists scheduling_settings_late_join');
        Schema::table('scheduling_settings', fn (Blueprint $table) => $table->dropColumn(['late_join', 'allow_mixed_ages', 'group_min_default']));
        DB::statement('alter table session_participants drop constraint if exists session_participants_outcome');
        DB::statement('alter table session_participants drop constraint if exists session_participants_funding');
        Schema::table('session_participants', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'enrollment_id']);
            $table->dropColumn(['enrollment_id', 'funding_status', 'outcome']);
        });
        DB::statement('alter table sessions drop constraint if exists sessions_course_or_group');
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'group_id']);
            $table->dropColumn('group_id');
        });
        DB::statement('alter table recurring_slots drop constraint if exists recurring_slots_owner');
        Schema::table('recurring_slots', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'group_id']);
            $table->dropColumn('group_id');
        });
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('lesson_groups');
    }
};
