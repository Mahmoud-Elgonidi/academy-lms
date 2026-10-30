<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Whether someone of the platform team shows the console's zone chip: hidden
 * from the chip's card, shown again from their account menu
 * (SavePlatformAdminTimezone, as their zone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->boolean('zone_chip')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', fn (Blueprint $table) => $table->dropColumn('zone_chip'));
    }
};
