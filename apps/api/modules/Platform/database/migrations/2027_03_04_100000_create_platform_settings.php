<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The platform's own settings, set from the console by an owner (Platform
 * settings: the outside services' keys and addresses), in place of the
 * server's .env lines of the same meaning. Each value is encrypted with
 * APP_KEY; only the platform connection reads the table (row-level security
 * on with no policy, and nothing granted to the academies' role).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value');                       // encrypted
            $table->uuid('updated_by')->nullable();      // platform_admins.id
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement('alter table platform_settings enable row level security');
        DB::statement('alter table platform_settings force row level security');
        DB::statement('revoke all on platform_settings from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
