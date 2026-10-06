<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The enrollment follows one program, pinned to one published version (docs/architecture/02, section 6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->uuid('program_id')->nullable()->after('teacher_id');
            $table->uuid('curriculum_version_id')->nullable()->after('program_id');

            $table->tenantForeign('program_id', 'programs');
            $table->tenantForeign('curriculum_version_id', 'curriculum_versions');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['academy_id', 'curriculum_version_id']);
            $table->dropForeign(['academy_id', 'program_id']);
            $table->dropColumn(['program_id', 'curriculum_version_id']);
        });
    }
};
