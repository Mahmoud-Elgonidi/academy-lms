<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What the stack's backup part did (infra/deploy/backup.sh): each base
 * backup, dump and copy of the files, and each restore drill, with how it
 * went. The backup part writes it as Postgres' superuser; the platform
 * console reads it (docs/architecture/03, section 12). No academy's data is
 * in it, so academies' connections never see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create table platform_backups (
                id uuid primary key default gen_random_uuid(),
                kind text not null check (kind in ('base', 'dump', 'files', 'drill')),
                status text not null check (status in ('ok', 'failed')),
                started_at timestamptz not null,
                finished_at timestamptz not null,
                size_bytes bigint check (size_bytes >= 0),
                name text,                -- the stamp, the file, or the moment a drill restored to
                detail text,              -- what failed, or what a drill found
                created_at timestamptz not null default now()
            )
        SQL);
        DB::statement('create index platform_backups_latest on platform_backups (kind, finished_at desc)');

        // Only the platform reads it; nothing in the app changes it.
        DB::statement('alter table platform_backups enable row level security');
        DB::statement('alter table platform_backups force row level security');
        DB::statement('revoke all on platform_backups from lms_app');
        DB::statement('revoke update, delete on platform_backups from lms_platform');
    }

    public function down(): void
    {
        DB::statement('drop table if exists platform_backups');
    }
};
