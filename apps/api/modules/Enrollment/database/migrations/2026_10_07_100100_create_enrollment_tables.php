<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The learning agreement: who studies what, with whom, from when
 * (docs/architecture/02-domain-rules.md, section 3, "أربع مفاهيم منفصلة").
 * The commercial side (plan, price, renewal) is Billing's subscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('teacher_id');                 // the current primary teacher
            $table->string('status', 20)->default('pending_payment'); // pending_payment | active | paused | ended
            $table->date('start_date');
            $table->uuid('credit_account_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'student_id']);
            $table->index(['academy_id', 'teacher_id']);
            $table->index(['academy_id', 'credit_account_id']);
            $table->tenantForeign('student_id', 'users');
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('subject_id', 'subjects');
            $table->tenantForeign('credit_account_id', 'credit_accounts');
        });
        DB::statement("alter table enrollments add constraint enrollments_status check (status in ('pending_payment', 'active', 'paused', 'ended'))");
        TenantTable::enableRls('enrollments');

        // Which teacher, from when: a change of teacher is a new row, never an edit.
        Schema::create('enrollment_teacher_assignments', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->uuid('teacher_id');
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['academy_id', 'enrollment_id']);
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('teacher_id', 'users');
        });
        TenantTable::enableRls('enrollment_teacher_assignments');

        Schema::create('enrollment_status_history', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('reason', 60)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['academy_id', 'enrollment_id', 'occurred_at']);
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
        });
        TenantTable::enableRls('enrollment_status_history');
        DB::statement('revoke update, delete on enrollment_status_history from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_status_history');
        Schema::dropIfExists('enrollment_teacher_assignments');
        Schema::dropIfExists('enrollments');
    }
};
