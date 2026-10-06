<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The DST planner (docs/architecture/02, section 1): a weekly time whose
 * hour moves for the student because one of the two clocks changes. One row
 * per enrollment and changed lesson (the weekly time it is on can be split
 * later and the row follows it): what the student saw before and will see
 * after, until when, what the academy decided, and when the family was told.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_shift_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('slot_id');
            $table->uuid('enrollment_id');
            $table->uuid('teacher_id');
            $table->uuid('student_id');
            $table->string('anchor_tz', 64);                 // the weekly time's clock (the teacher's)
            $table->string('other_tz', 64);                  // the student's clock
            $table->string('cause_tz', 64);                  // whose clock changes
            $table->timestampTz('changes_at');               // the first lesson at the new hour
            $table->timestampTz('reverts_at')->nullable();   // when the two clocks agree again, if within a year
            $table->unsignedSmallInteger('before_weekday');  // on the student's clock
            $table->time('before_time');
            $table->unsignedSmallInteger('after_weekday');
            $table->time('after_time');
            $table->smallInteger('delta_min');               // how far the student's hour moves
            $table->unsignedSmallInteger('lessons')->default(0);
            $table->string('decision', 16)->default('pending'); // pending | keep_teacher | keep_student
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('notified_early_at')->nullable();   // 14 days before
            $table->timestampTz('notified_late_at')->nullable();    // 2 days before
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'enrollment_id', 'changes_at']);
            $table->index(['academy_id', 'changes_at']);
            $table->tenantForeign('slot_id', 'recurring_slots')->cascadeOnDelete();
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('student_id', 'users');
        });
        DB::statement("alter table schedule_shift_notices add constraint schedule_shift_notices_decision check (decision in ('pending', 'keep_teacher', 'keep_student'))");
        TenantTable::enableRls('schedule_shift_notices');
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_shift_notices');
    }
};
