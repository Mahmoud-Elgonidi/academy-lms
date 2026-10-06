<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * An enrollment's weekly times (docs/architecture/02, sections 1–2): a
 * weekday and a wall-clock time on the teacher's clock, from a date.
 * Sessions are generated from them over a rolling horizon. The second
 * exclusion constraint of the design: no two live weekly times of one
 * teacher overlap, on any week they share.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->uuid('teacher_id');
            $table->uuid('student_id');
            $table->unsignedSmallInteger('weekday');    // ISO, on the anchor clock
            $table->time('local_start');
            $table->unsignedSmallInteger('duration_min');
            $table->string('anchor_tz', 64);
            $table->string('status', 10)->default('active'); // active | ended
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'enrollment_id']);
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('student_id', 'users');
        });
        // Minutes from Monday 00:00 on the anchor clock; a slot over Sunday midnight wraps to Monday.
        DB::statement('alter table recurring_slots add column minute_of_week int4multirange not null');
        DB::statement('alter table recurring_slots add column effective daterange not null');
        DB::statement(<<<'SQL'
            alter table recurring_slots add constraint recurring_slots_no_overlap
            exclude using gist (academy_id with =, teacher_id with =, minute_of_week with &&, effective with &&) where (status = 'active')
        SQL);
        TenantTable::enableRls('recurring_slots');

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('enrollment_id')->nullable()->after('subject_id');
            $table->uuid('slot_id')->nullable()->after('enrollment_id');
            $table->string('occurrence_key', 60)->nullable()->after('slot_id'); // slot id + local date: generating twice is a no-op

            $table->unique(['academy_id', 'occurrence_key']);
            $table->index(['academy_id', 'enrollment_id', 'starts_at']);
            $table->tenantForeign('enrollment_id', 'enrollments');
            $table->tenantForeign('slot_id', 'recurring_slots');
        });

        Schema::table('scheduling_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('horizon_weeks')->default(8);
        });
    }

    public function down(): void
    {
        Schema::table('scheduling_settings', fn (Blueprint $table) => $table->dropColumn('horizon_weeks'));
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'slot_id']);
            $table->dropForeign(['academy_id', 'enrollment_id']);
            $table->dropUnique(['academy_id', 'occurrence_key']);
            $table->dropColumn(['enrollment_id', 'slot_id', 'occurrence_key']);
        });
        Schema::dropIfExists('recurring_slots');
    }
};
