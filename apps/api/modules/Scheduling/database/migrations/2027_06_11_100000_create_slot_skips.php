<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Weeks a weekly time was set up without (docs/architecture/02, section 27,
 * J3): a course the family starts may skip a week of its first ones that
 * falls on an academy holiday or the teacher's approved leave, and the
 * family is told so. MaterializeSessions never makes that date's lesson,
 * now or in a later run, whatever becomes of the leave. The date is the
 * slot's own (the teacher's clock). course_starts.extra_validity_days: the
 * days the first block's minutes last longer for them, given once at the
 * first payment (GiveSkippedWeeksBack).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_skips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('slot_id');
            $table->date('on_date');
            $table->string('reason', 20);
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->unique(['academy_id', 'slot_id', 'on_date']);
            $table->tenantForeign('slot_id', 'recurring_slots')->cascadeOnDelete();
        });
        DB::statement("alter table slot_skips add constraint slot_skips_reason check (reason in ('holiday', 'teacher_leave'))");
        TenantTable::enableRls('slot_skips');

        Schema::table('course_starts', function (Blueprint $table) {
            $table->unsignedSmallInteger('extra_validity_days')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('course_starts', fn (Blueprint $table) => $table->dropColumn('extra_validity_days'));
        Schema::dropIfExists('slot_skips');
    }
};
