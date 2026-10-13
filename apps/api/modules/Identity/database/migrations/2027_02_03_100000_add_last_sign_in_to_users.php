<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * When someone last signed in (a panel, the welcome link, the portal or an
 * app), for the academy's Team screen: who never came in, who stopped. The
 * platform team signed in as someone does not count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('last_sign_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_sign_in_at'));
    }
};
