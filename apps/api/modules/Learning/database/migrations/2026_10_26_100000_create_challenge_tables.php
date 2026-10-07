<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The game layer of a student's own area (docs/architecture/02, "التحديات"):
 * challenges are worked out from what really happened each time; only the
 * rewards a student collected are kept, once per challenge and period, and
 * they add to their points. Cheers are a guardian's encouragement, seen once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->string('challenge', 30);                // attend | homework | on_time | master | focus | month | boss | chest
            $table->string('period', 60);                   // 2026-10-03 (the week's Saturday), 2026-10 (a month), world:{enrollment}:{n}
            $table->unsignedInteger('points');
            $table->string('sticker', 20)->nullable();      // the kids' chest
            $table->timestampTz('claimed_at');

            $table->tenantKey();
            $table->unique(['academy_id', 'student_id', 'challenge', 'period']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
        });
        DB::statement('alter table challenge_claims add constraint challenge_claims_points check (points between 0 and 10000)');
        TenantTable::enableRls('challenge_claims');

        Schema::create('cheers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->uuid('from_id');                        // a guardian
            $table->string('kind', 10);                     // clap | star | heart | fire
            $table->timestampTz('created_at');
            $table->timestampTz('seen_at')->nullable();

            $table->tenantKey();
            $table->index(['academy_id', 'student_id', 'seen_at']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('from_id', 'users')->cascadeOnDelete();
        });
        DB::statement("alter table cheers add constraint cheers_kind check (kind in ('clap', 'star', 'heart', 'fire'))");
        TenantTable::enableRls('cheers');
    }

    public function down(): void
    {
        Schema::dropIfExists('cheers');
        Schema::dropIfExists('challenge_claims');
    }
};
