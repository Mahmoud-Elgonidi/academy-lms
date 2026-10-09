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
        // The rest of a teacher's profile (docs/architecture/01, section 5):
        // the ages they teach (none: any) and how many lessons a day at most.
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->jsonb('age_groups')->default('[]');
            $table->unsignedSmallInteger('max_daily_lessons')->nullable();
        });
        DB::statement(<<<'SQL'
            alter table teacher_profiles add constraint teacher_profiles_age_groups
                check (jsonb_typeof(age_groups) = 'array' and age_groups <@ '["kids", "middle", "teen", "adults"]'::jsonb)
            SQL);
        DB::statement('alter table teacher_profiles add constraint teacher_profiles_max_daily check (max_daily_lessons is null or max_daily_lessons between 1 and 30)');

        // Per subject, the levels they teach, from one to another (none: any);
        // a level of that same subject, and one in use stays (Catalog is told).
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->uuid('level_from_id')->nullable();
            $table->uuid('level_to_id')->nullable();
            $table->foreign(['academy_id', 'subject_id', 'level_from_id'])->references(['academy_id', 'subject_id', 'id'])->on('subject_levels')->restrictOnDelete();
            $table->foreign(['academy_id', 'subject_id', 'level_to_id'])->references(['academy_id', 'subject_id', 'id'])->on('subject_levels')->restrictOnDelete();
        });

        // And the subject's categories they teach in (none: any).
        Schema::create('teacher_subject_categories', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->uuid('category_id');

            $table->primary(['academy_id', 'teacher_id', 'subject_id', 'category_id']);
            $table->index(['academy_id', 'category_id']);
            $table->foreign(['academy_id', 'teacher_id', 'subject_id'])->references(['academy_id', 'teacher_id', 'subject_id'])->on('teacher_subjects')->cascadeOnDelete();
            $table->foreign(['academy_id', 'subject_id', 'category_id'])->references(['academy_id', 'subject_id', 'id'])->on('subject_categories')->restrictOnDelete();
        });
        TenantTable::enableRls('teacher_subject_categories');

        // A student's details (docs/architecture/01, section 6), and who should
        // teach them: a woman or a man only, or preferably.
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('gender', 10)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('school_grade', 12)->nullable();
            $table->string('teacher_gender', 15)->nullable();
        });
        DB::statement(<<<'SQL'
            alter table student_profiles
                add constraint student_profiles_gender check (gender is null or gender in ('female', 'male')),
                add constraint student_profiles_country check (country is null or country ~ '^[A-Z]{2}$'),
                add constraint student_profiles_grade check (school_grade is null or school_grade ~ '^(kg[12]|g([1-9]|1[0-2])|university)$'),
                add constraint student_profiles_teacher_gender check (teacher_gender is null or teacher_gender in ('female_only', 'male_only', 'prefer_female', 'prefer_male'))
            SQL);
    }

    public function down(): void
    {
        Schema::table('student_profiles', fn (Blueprint $t) => $t->dropColumn(['gender', 'country', 'school_grade', 'teacher_gender']));
        Schema::dropIfExists('teacher_subject_categories');
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'subject_id', 'level_from_id']);
            $table->dropForeign(['academy_id', 'subject_id', 'level_to_id']);
            $table->dropColumn(['level_from_id', 'level_to_id']);
        });
        Schema::table('teacher_profiles', fn (Blueprint $t) => $t->dropColumn(['age_groups', 'max_daily_lessons']));
    }
};
