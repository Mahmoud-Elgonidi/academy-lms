<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Who was in the classroom and when (docs/architecture/03, section 6,
 * "مصدر الحقيقة في الحضور"): one row per stretch between a join and a
 * leave, from the video provider's webhooks, its room API, or a click on an
 * outside link. Taught time is the overlap of the teacher's and the
 * student's stretches inside the lesson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('user_id');
            $table->string('role', 20);                  // teacher | student
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->string('source', 20)->default('provider'); // provider | reconcile | external_link | manual
            $table->timestampTz('created_at');

            $table->tenantKey();
            $table->index(['academy_id', 'session_id', 'role']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('user_id', 'users');
        });
        DB::statement('create unique index attendance_segments_one_open on attendance_segments (academy_id, session_id, user_id) where left_at is null');
        DB::statement('alter table attendance_segments add constraint attendance_segments_order check (left_at is null or left_at >= joined_at)');
        TenantTable::enableRls('attendance_segments');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_segments');
    }
};
