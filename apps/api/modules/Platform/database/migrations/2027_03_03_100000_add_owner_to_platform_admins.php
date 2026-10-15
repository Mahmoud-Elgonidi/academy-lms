<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The platform's owners: the console's admins who may change the platform's
 * own settings (Platform settings: the services' keys). The team's earliest
 * active admin becomes the first; `platform:admin {email} --owner` adds
 * another, and the last active owner is never taken away or locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('is_active');
        });
        // Row-level security is forced with no policy (only the platform connection reads the team): lifted for
        // this one update inside the migration's transaction, as the owner role sees no row otherwise.
        DB::statement('alter table platform_admins no force row level security');
        DB::statement(<<<'SQL'
            update platform_admins set is_owner = true
            where id = (select id from platform_admins where is_active order by created_at, id limit 1)
            SQL);
        DB::statement('alter table platform_admins force row level security');
    }

    public function down(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
