<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * How the academy writes a clock time for everyone: 12 hours (5:00 م) or 24
 * hours (17:00). Each person may choose their own (users.time_format).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table) {
            $table->string('time_format', 3)->default('h12');
        });
        DB::statement("alter table academies add constraint academies_time_format_check check (time_format in ('h12', 'h24'))");
    }

    public function down(): void
    {
        Schema::table('academies', fn (Blueprint $table) => $table->dropColumn('time_format'));
    }
};
