<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The platform team (docs/architecture/03: Super Admins "في جدول منفصل
 * `platform_admins` بـ guard خاص و 2FA"). Only the platform connection
 * reads it: row-level security is on with no policy, so the academies'
 * connection sees no row at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->citext('email')->unique();
            $table->string('password');
            $table->text('app_authentication_secret')->nullable();            // encrypted
            $table->text('app_authentication_recovery_codes')->nullable();    // encrypted
            $table->boolean('is_active')->default(true);
            $table->string('locale', 5)->default('ar');
            $table->string('timezone', 64)->default('UTC');                  // the console's times are on this clock
            $table->rememberToken();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('alter table platform_admins enable row level security');
        DB::statement('alter table platform_admins force row level security');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
    }
};
