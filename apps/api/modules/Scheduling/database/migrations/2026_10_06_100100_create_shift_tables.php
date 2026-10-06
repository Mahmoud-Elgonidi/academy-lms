<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Teacher shifts: approved weekly schedules (versioned) and the proposals that
 * change them (docs/architecture/02-domain-rules.md, section 2, "مصدر الشفتات").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_settings', function (Blueprint $table) {
            $table->tenant();
            $table->string('shift_mode', 30)->default('teacher_proposes'); // teacher_proposes | admin_only
            $table->unsignedSmallInteger('min_block_min')->default(60);
            $table->unsignedSmallInteger('reduction_notice_days')->default(14);
            $table->unsignedSmallInteger('proposal_reply_hours')->default(48);
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('scheduling_settings');

        // One version of a teacher's weekly schedule, valid for a date range.
        Schema::create('teacher_shift_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->unsignedInteger('version');
            $table->string('timezone', 64);
            $table->string('status', 20)->default('active');  // active | superseded
            $table->uuid('approved_by')->nullable();
            $table->uuid('source_proposal_id')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'teacher_id', 'version']);
            $table->tenantForeign('teacher_id', 'users');
        });
        DB::statement('alter table teacher_shift_schedules add column effective daterange not null');
        // One version in force per teacher on any day.
        DB::statement(<<<'SQL'
            alter table teacher_shift_schedules add constraint teacher_shift_schedules_one_active
            exclude using gist (academy_id with =, teacher_id with =, effective with &&) where (status = 'active')
        SQL);
        TenantTable::enableRls('teacher_shift_schedules');

        Schema::create('teacher_shift_rules', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('schedule_id');
            $table->uuid('teacher_id');
            $table->unsignedSmallInteger('weekday');        // ISO: 1 Monday … 7 Sunday
            $table->time('local_start');                     // wall clock in the schedule's timezone
            $table->unsignedSmallInteger('duration_min');
            $table->string('kind', 10)->default('work');

            $table->index(['academy_id', 'teacher_id']);
            $table->tenantForeign('schedule_id', 'teacher_shift_schedules')->cascadeOnDelete();
        });
        TenantTable::enableRls('teacher_shift_rules');

        Schema::create('shift_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->uuid('base_schedule_id')->nullable();
            $table->string('author_role', 10);               // teacher | admin
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->string('timezone', 64);
            $table->date('effective_from');
            $table->text('note')->nullable();
            $table->unsignedInteger('min_weekly_min')->nullable();
            $table->unsignedInteger('max_weekly_min')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->text('override_reason')->nullable();
            $table->jsonb('resolutions')->default('{}');      // conflict key => {kind, by, at}
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'status']);
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('base_schedule_id', 'teacher_shift_schedules');
        });
        // One open proposal per teacher.
        DB::statement(<<<'SQL'
            create unique index shift_proposals_one_open on shift_proposals (academy_id, teacher_id)
            where status in ('submitted', 'changes_requested', 'countered')
        SQL);
        TenantTable::enableRls('shift_proposals');

        // The blocks of each revision; a counter-offer is a new revision.
        Schema::create('shift_proposal_rules', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('proposal_id');
            $table->unsignedInteger('revision');
            $table->string('author_role', 10);
            $table->unsignedSmallInteger('weekday');
            $table->time('local_start');
            $table->unsignedSmallInteger('duration_min');

            $table->index(['academy_id', 'proposal_id', 'revision']);
            $table->tenantForeign('proposal_id', 'shift_proposals')->cascadeOnDelete();
        });
        TenantTable::enableRls('shift_proposal_rules');

        Schema::create('shift_proposal_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('proposal_id');
            $table->uuid('author_id')->nullable();
            $table->string('author_role', 10);               // teacher | admin | system
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['academy_id', 'proposal_id', 'created_at']);
            $table->tenantForeign('proposal_id', 'shift_proposals')->cascadeOnDelete();
        });
        TenantTable::enableRls('shift_proposal_comments');

        // Tentative bookings while a coordinator finishes enrolling (10 minutes).
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('batch_id');
            $table->uuid('teacher_id');
            $table->uuid('student_id')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('expires_at');
            $table->uuid('created_by');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['academy_id', 'teacher_id', 'expires_at']);
            $table->index(['academy_id', 'batch_id']);
            $table->tenantForeign('teacher_id', 'users');
        });
        TenantTable::enableRls('booking_holds');
    }

    public function down(): void
    {
        foreach (['booking_holds', 'shift_proposal_comments', 'shift_proposal_rules', 'shift_proposals', 'teacher_shift_rules', 'teacher_shift_schedules', 'scheduling_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
