<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How the academy's students see their journey (docs/architecture/02,
 * section 18): playful (each in their stage's look, islands and games),
 * calm (levels and missions, no islands), plain (no stars either).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->string('journey_style', 10)->default('playful');
        });
        DB::statement("alter table learning_settings add constraint learning_settings_journey_style check (journey_style in ('playful', 'calm', 'plain'))");
    }

    public function down(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->dropColumn('journey_style');
        });
    }
};
