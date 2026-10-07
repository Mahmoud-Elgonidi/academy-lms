<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The platform team signing in as someone of an academy (docs/architecture/01:
 * "الدخول بحساب مستخدم مع تسجيله"): who, as whom, why, for how long. The
 * console makes a one-time link (only its hash is kept) that works for two
 * minutes on the academy's own host; once used, the sign-in lasts the minutes
 * chosen and ends on exit, on expiry, or from the console. The academy and
 * the person see every one of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('user_id');
            $table->uuid('admin_id');                       // platform_admins.id
            $table->string('admin_name', 120);
            $table->text('reason');
            $table->smallInteger('minutes');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestampTz('redeem_by');
            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->string('ended_reason', 20)->nullable(); // exit | expired | ended
            $table->string('ip', 45)->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->tenantForeign('user_id', 'users');
            $table->index(['academy_id', 'user_id', 'created_at']);
            $table->index(['academy_id', 'created_at']);
        });
        DB::statement('alter table impersonations add constraint impersonations_minutes check (minutes between 5 and 120)');
        DB::statement("alter table impersonations add constraint impersonations_ended check ((ended_at is null) = (ended_reason is null) and (ended_reason is null or ended_reason in ('exit', 'expired', 'ended')))");

        TenantTable::enableRls('impersonations');
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
