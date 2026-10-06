<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Time off (docs/architecture/01, section 6 and flow "ز"; 02, section 1):
 * a teacher's leave, asked for and decided, whose lessons get a substitute
 * or a makeup; and the academy's own holidays, local days on the academy's
 * clock, whose lessons are cancelled under rule 15.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_time_off', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64);                 // the teacher's clock: the days and times were asked on it
            $table->boolean('all_day')->default(true);      // whole days (midnight to midnight there), or hours
            $table->string('reason', 20);                   // sick | vacation | personal | family | emergency | other
            $table->text('note')->nullable();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected | withdrawn
            $table->uuid('requested_by');
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'teacher_id', 'starts_at']);
            $table->index(['academy_id', 'status', 'starts_at']);
            $table->tenantForeign('teacher_id', 'users');
        });
        DB::statement('alter table teacher_time_off add constraint teacher_time_off_ends_after_start check (ends_at > starts_at)');
        DB::statement("alter table teacher_time_off add constraint teacher_time_off_status check (status in ('pending', 'approved', 'rejected', 'withdrawn'))");
        DB::statement("alter table teacher_time_off add constraint teacher_time_off_reason check (reason in ('sick', 'vacation', 'personal', 'family', 'emergency', 'other'))");
        // One request at a time for the same hours: what is asked for or granted never overlaps.
        DB::statement(<<<'SQL'
            alter table teacher_time_off add constraint teacher_time_off_no_overlap
            exclude using gist (academy_id with =, teacher_id with =, tstzrange(starts_at, ends_at) with &&) where (status in ('pending', 'approved'))
        SQL);
        TenantTable::enableRls('teacher_time_off');

        Schema::create('academy_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name', 120);
            $table->string('timezone', 64);                 // the academy's clock: the days are its days
            $table->date('first_day');
            $table->date('last_day');                       // inclusive
            $table->timestampTz('starts_at');               // first_day 00:00 there
            $table->timestampTz('ends_at');                 // the day after last_day, 00:00 there
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'starts_at']);
        });
        DB::statement('alter table academy_holidays add constraint academy_holidays_days check (last_day >= first_day)');
        DB::statement(<<<'SQL'
            alter table academy_holidays add constraint academy_holidays_no_overlap
            exclude using gist (academy_id with =, daterange(first_day, last_day, '[]') with &&)
        SQL);
        TenantTable::enableRls('academy_holidays');
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_holidays');
        Schema::dropIfExists('teacher_time_off');
    }
};
