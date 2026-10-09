<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The off-site copy (infra/deploy/offsite.sh) writes its rows here too: each
 * piece sent to the bucket, and once an hour how far the WAL has gone
 * (name "wal"), as Postgres' superuser like the backup part.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table platform_backups drop constraint platform_backups_kind_check');
        DB::statement("alter table platform_backups add constraint platform_backups_kind_check check (kind in ('base', 'dump', 'files', 'drill', 'offsite'))");
    }

    public function down(): void
    {
        DB::statement("delete from platform_backups where kind = 'offsite'");
        DB::statement('alter table platform_backups drop constraint platform_backups_kind_check');
        DB::statement("alter table platform_backups add constraint platform_backups_kind_check check (kind in ('base', 'dump', 'files', 'drill'))");
    }
};
