<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * A course the family started on its own (docs/architecture/02, section 27,
 * J3; StartOwnCourse): which course, who started it and pays, the plan and
 * the price it was started at, the teacher or group, and whether the
 * trial's offer went on its first invoice. The family may call it off
 * before paying (CancelOwnStart: cancelled_at). One row per course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_starts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->uuid('learner_id');
            $table->uuid('started_by');
            $table->uuid('payer_id');
            $table->uuid('plan_id');
            $table->uuid('teacher_id');
            $table->uuid('group_id')->nullable();
            $table->bigInteger('price_minor');
            $table->char('currency', 3);
            $table->boolean('offer_applied')->default(false);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'enrollment_id']);
            $table->index(['academy_id', 'learner_id']);
            $table->tenantForeign('enrollment_id', 'enrollments');
            $table->tenantForeign('learner_id', 'users');
            $table->tenantForeign('started_by', 'users');
            $table->tenantForeign('payer_id', 'users');
            $table->tenantForeign('teacher_id', 'users');
        });
        DB::statement('alter table course_starts add constraint course_starts_price check (price_minor >= 0)');
        TenantTable::enableRls('course_starts');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_starts');
    }
};
