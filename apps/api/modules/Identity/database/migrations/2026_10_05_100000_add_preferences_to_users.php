<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user screen choices (e.g. the live board layout). Keys are declared
        // by the module that owns the screen, through UserPreferences.
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('preferences')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
