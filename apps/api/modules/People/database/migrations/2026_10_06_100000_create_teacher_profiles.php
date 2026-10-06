<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // What matching needs to know about a teacher (docs/architecture/03, section 4).
        // Tiers, rate cards and age groups arrive with Evaluation and Payroll.
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('user_id');
            $table->string('gender', 10);                       // female | male
            $table->jsonb('languages')->default('["ar"]');      // languages they explain in
            $table->string('dialect', 40)->nullable();
            $table->boolean('accepts_new')->default(true);
            $table->boolean('accepts_trials')->default(true);
            $table->unsignedInteger('max_weekly_min')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();

            $table->primary(['academy_id', 'user_id']);
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
        });

        DB::statement("alter table teacher_profiles add constraint teacher_profiles_gender check (gender in ('female', 'male'))");
        TenantTable::enableRls('teacher_profiles');

        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');

            $table->primary(['academy_id', 'teacher_id', 'subject_id']);
            $table->index(['academy_id', 'subject_id']);
            $table->tenantForeign('teacher_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('subject_id', 'subjects')->cascadeOnDelete();
        });

        TenantTable::enableRls('teacher_subjects');
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('teacher_profiles');
    }
};
