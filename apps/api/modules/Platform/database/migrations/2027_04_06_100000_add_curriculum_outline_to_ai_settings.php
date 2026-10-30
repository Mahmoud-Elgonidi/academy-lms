<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A new AI helper (docs/architecture/02, section 22): the curriculum outline,
 * levels, units and lessons drafted from the goal the academy types, reviewed
 * before anything is saved. Off until the academy turns it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('curriculum_outline')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', fn (Blueprint $table) => $table->dropColumn('curriculum_outline'));
    }
};
