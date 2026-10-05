<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

return new class extends Migration
{
    public function up(): void
    {
        // A guardian can have several children, and a child several guardians.
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('guardian_id');
            $table->uuid('student_id');
            $table->string('relationship', 20)->default('parent');
            $table->boolean('is_primary')->default(true);
            $table->boolean('receives_reports')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['academy_id', 'guardian_id', 'student_id']);
            $table->index(['academy_id', 'student_id']);
            $table->tenantForeign('guardian_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
        });

        TenantTable::enableRls('guardian_student');
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};
