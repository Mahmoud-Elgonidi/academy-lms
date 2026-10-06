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
        // What the student's own screens need to know about them: their age,
        // which picks the look of their area (kids, middle school, teens), or
        // the look the academy chose for them.
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('user_id');
            $table->date('birth_date')->nullable();
            $table->string('stage', 10)->nullable();          // null: from the age; else kids | middle | teen | classic
            $table->timestampsTz();

            $table->primary(['academy_id', 'user_id']);
            $table->tenantForeign('user_id', 'users')->cascadeOnDelete();
        });

        DB::statement("alter table student_profiles add constraint student_profiles_stage check (stage is null or stage in ('kids', 'middle', 'teen', 'classic'))");
        TenantTable::enableRls('student_profiles');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
