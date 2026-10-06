<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * After the lesson (docs/architecture/02, section 6; 01, "التقارير والواجبات"):
 * the teacher's report and what it covered, the progress it moves, the
 * student's position on the roadmap, and homework with its submissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_settings', function (Blueprint $table) {
            $table->tenant();
            $table->unsignedSmallInteger('report_due_hours')->default(12);
            $table->unsignedSmallInteger('homework_days')->default(3);
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('learning_settings');

        Schema::create('session_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('teacher_id');
            $table->uuid('student_id');
            $table->string('kind', 10)->default('lesson');      // lesson | absence
            $table->string('status', 10)->default('due');       // due | overdue | submitted | reviewed
            $table->timestampTz('session_starts_at');
            $table->timestampTz('due_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->boolean('late')->default(false);
            $table->unsignedSmallInteger('engagement')->nullable(); // 1–5
            $table->unsignedSmallInteger('overall')->nullable();    // 1–5
            $table->text('notes_for_student')->nullable();
            $table->text('notes_internal')->nullable();
            $table->text('absence_note')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'session_id']);
            $table->index(['academy_id', 'teacher_id', 'status']);
            $table->index(['academy_id', 'enrollment_id', 'session_starts_at']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('enrollment_id', 'enrollments');
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('student_id', 'users');
        });
        DB::statement('alter table session_reports add constraint session_reports_scores check ((engagement is null or engagement between 1 and 5) and (overall is null or overall between 1 and 5))');
        TenantTable::enableRls('session_reports');

        Schema::create('report_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('report_id');
            $table->uuid('node_id');
            $table->string('outcome', 15);                      // introduced | practiced | mastered | needs_review
            $table->unsignedSmallInteger('score')->nullable();  // 1–5
            $table->string('note', 500)->nullable();

            $table->index(['academy_id', 'report_id']);
            $table->tenantForeign('report_id', 'session_reports')->cascadeOnDelete();
            $table->tenantForeign('node_id', 'curriculum_nodes');
        });
        DB::statement("alter table report_items add constraint report_items_outcome check (outcome in ('introduced', 'practiced', 'mastered', 'needs_review') and (score is null or score between 1 and 5))");
        TenantTable::enableRls('report_items');

        // A read model rebuilt from the reports (docs/architecture/04, "Projector").
        Schema::create('enrollment_progress', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->uuid('node_id');
            $table->string('status', 15);                       // in_progress | mastered | needs_review
            $table->unsignedSmallInteger('times_seen')->default(0);
            $table->unsignedSmallInteger('high_scores')->default(0);
            $table->unsignedSmallInteger('last_score')->nullable();
            $table->string('last_outcome', 15)->nullable();
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('mastered_at')->nullable();
            $table->unsignedSmallInteger('review_interval_days')->nullable(); // spaced review after mastery: 7, 14, 28 … days
            $table->timestampTz('next_review_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->primary(['academy_id', 'enrollment_id', 'node_id']);
            $table->index(['academy_id', 'enrollment_id', 'next_review_at']);
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('node_id', 'curriculum_nodes')->cascadeOnDelete();
        });
        TenantTable::enableRls('enrollment_progress');

        Schema::create('enrollment_positions', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->uuid('version_id');
            $table->uuid('current_node_id')->nullable();         // the first lesson not yet mastered
            $table->unsignedInteger('mastered_weight')->default(0);
            $table->unsignedInteger('total_weight')->default(0);
            $table->unsignedSmallInteger('percent')->default(0);
            $table->decimal('pace', 6, 3)->nullable();           // weight mastered per lesson, recent average
            $table->decimal('eta_weeks', 6, 1)->nullable();
            $table->timestampTz('last_report_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->primary(['academy_id', 'enrollment_id']);
            $table->tenantForeign('enrollment_id', 'enrollments')->cascadeOnDelete();
            $table->tenantForeign('version_id', 'curriculum_versions');
        });
        TenantTable::enableRls('enrollment_positions');

        Schema::create('homework', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('session_id')->nullable();
            $table->uuid('report_id')->nullable();
            $table->uuid('teacher_id');
            $table->uuid('student_id');
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->string('accepts', 10)->default('any');       // any | text | audio | file
            $table->timestampTz('due_at');
            $table->string('status', 10)->default('assigned');  // assigned | submitted | reviewed | missed
            $table->unsignedSmallInteger('grade')->nullable();   // 1–5
            $table->text('feedback')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'teacher_id', 'status']);
            $table->index(['academy_id', 'student_id', 'status']);
            $table->tenantForeign('enrollment_id', 'enrollments');
            $table->tenantForeign('session_id', 'sessions')->nullOnDelete();
            $table->tenantForeign('report_id', 'session_reports')->nullOnDelete();
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('student_id', 'users');
        });
        TenantTable::enableRls('homework');

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('homework_id');
            $table->text('body')->nullable();
            $table->string('file_path', 300)->nullable();        // on the private disk, under the academy's folder
            $table->string('file_name', 200)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->uuid('submitted_by');
            $table->timestampTz('submitted_at');

            $table->tenantKey();
            $table->index(['academy_id', 'homework_id']);
            $table->tenantForeign('homework_id', 'homework')->cascadeOnDelete();
            $table->tenantForeign('submitted_by', 'users');
        });
        TenantTable::enableRls('homework_submissions');
    }

    public function down(): void
    {
        foreach (['homework_submissions', 'homework', 'enrollment_positions', 'enrollment_progress', 'report_items', 'session_reports', 'learning_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
