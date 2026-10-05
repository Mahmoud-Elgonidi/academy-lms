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
        // One lesson. Enrollment, recurring slot, lead and policy columns arrive
        // with their modules (docs/architecture/03-technical-architecture.md, section 4).
        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('type', 20);                 // regular | trial | makeup | extra | assessment
            $table->uuid('teacher_id');
            $table->uuid('original_teacher_id')->nullable();
            $table->uuid('subject_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20)->default('scheduled');
            $table->string('review_reason', 40)->nullable();
            $table->string('funding_status', 20)->default('funded'); // funded | unfunded | free | makeup
            $table->uuid('makeup_for_session_id')->nullable();
            $table->string('anchor_tz', 64);           // the wall clock the time was agreed in
            $table->jsonb('decision')->nullable();      // what the policy decided, and why
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'starts_at']);
            $table->index(['academy_id', 'status', 'starts_at']);
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('original_teacher_id', 'users');
            $table->tenantForeign('subject_id', 'subjects');
            $table->tenantForeign('makeup_for_session_id', 'sessions');
        });

        DB::statement('alter table sessions add constraint sessions_ends_after_start check (ends_at > starts_at)');
        TenantTable::enableRls('sessions');

        // Who is in a session. block_period is the time they are booked for;
        // the exclusion constraint makes double-booking anyone impossible.
        Schema::create('session_participants', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('user_id');
            $table->string('role', 20);                 // teacher | student
            $table->boolean('is_active')->default(true);
            $table->timestampTz('first_join_at')->nullable();
            $table->timestampTz('last_left_at')->nullable();
            $table->unsignedInteger('attended_min')->default(0);

            $table->primary(['academy_id', 'session_id', 'user_id']);
            $table->index(['academy_id', 'user_id']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('user_id', 'users');
        });

        DB::statement('alter table session_participants add column block_period tstzrange not null');
        DB::statement(<<<'SQL'
            alter table session_participants add constraint session_participants_no_double_booking
            exclude using gist (academy_id with =, user_id with =, block_period with &&) where (is_active)
        SQL);
        TenantTable::enableRls('session_participants');

        // What happened in a session, in order: joins, drops, contact attempts,
        // decisions. Append-only.
        Schema::create('session_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->string('kind', 40);
            $table->uuid('actor_id')->nullable();
            $table->jsonb('data')->default('{}');
            $table->timestampTz('occurred_at');

            $table->index(['academy_id', 'session_id', 'occurred_at']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
        });

        TenantTable::enableRls('session_events');
        DB::statement('revoke update, delete on session_events from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('session_events');
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('sessions');
    }
};
