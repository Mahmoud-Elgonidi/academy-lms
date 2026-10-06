<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Evaluation (docs/architecture/02, section 7): the family's one-tap rating
 * of a lesson, and the lessons a manager takes out of a teacher's numbers
 * (an outage, a platform fault) with the reason. The numbers themselves are
 * read from the lessons, attendance and reports each time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('rater_id');                       // the student or one of their guardians
            $table->uuid('student_id');
            $table->uuid('teacher_id');                     // who taught it (a substitute included)
            $table->unsignedSmallInteger('stars');
            $table->jsonb('tags')->default('[]');
            $table->text('comment')->nullable();
            $table->string('via', 10)->default('app');      // app | link
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'session_id', 'rater_id']);
            $table->index(['academy_id', 'teacher_id', 'created_at']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('rater_id', 'users');
            $table->tenantForeign('student_id', 'users');
            $table->tenantForeign('teacher_id', 'users');
        });
        DB::statement('alter table lesson_ratings add constraint lesson_ratings_stars check (stars between 1 and 5)');
        TenantTable::enableRls('lesson_ratings');

        Schema::create('kpi_exclusions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('teacher_id');
            $table->string('reason', 20);                   // platform | provider | academy | other
            $table->text('note')->nullable();
            $table->uuid('excluded_by');
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'session_id']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('excluded_by', 'users');
        });
        TenantTable::enableRls('kpi_exclusions');
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_exclusions');
        Schema::dropIfExists('lesson_ratings');
    }
};
