<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The daily review's pages moved to the academy's Quran settings
 * (quran_settings, copied there before this runs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_settings', fn (Blueprint $table) => $table->dropColumn('hifz_daily_pages'));
    }

    public function down(): void
    {
        Schema::table('learning_settings', fn (Blueprint $table) => $table->unsignedSmallInteger('hifz_daily_pages')->default(3));
    }
};
