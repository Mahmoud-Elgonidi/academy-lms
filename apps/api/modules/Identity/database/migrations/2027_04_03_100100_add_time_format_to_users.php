<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * How this person reads a clock: 12 hours (5:00 م) or 24 hours (17:00), or
 * null to follow their academy's choice (academies.time_format).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('time_format', 3)->nullable();
        });
        DB::statement("alter table users add constraint users_time_format_check check (time_format is null or time_format in ('h12', 'h24'))");
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('time_format'));
    }
};
