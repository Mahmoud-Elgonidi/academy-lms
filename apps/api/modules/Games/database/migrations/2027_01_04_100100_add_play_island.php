<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A round on a journey's station keeps the island its level is drawn as: a creature it hatches hatches there. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_plays', function (Blueprint $table) {
            $table->string('island_key', 31)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('game_plays', function (Blueprint $table) {
            $table->dropColumn('island_key');
        });
    }
};
