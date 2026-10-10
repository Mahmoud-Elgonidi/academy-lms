<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A third kind of request: correction (the right to have wrong data put
 * right). The person says what is wrong in their own words (`reason`); the
 * academy answers within 30 days, done (with a note) or refused (with the
 * reason they read). One open at a time per person, like the others.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table data_requests alter column kind type varchar(12)');
        DB::statement('alter table data_requests drop constraint data_requests_kind');
        DB::statement("alter table data_requests add constraint data_requests_kind check (kind in ('export', 'erasure', 'correction'))");
        DB::statement('alter table data_requests drop constraint data_requests_status_kind');
        DB::statement("alter table data_requests add constraint data_requests_status_kind check (
            (kind = 'export' and status in ('pending', 'ready', 'expired', 'failed'))
            or (kind = 'erasure' and status in ('pending', 'approved', 'done', 'refused', 'cancelled'))
            or (kind = 'correction' and status in ('pending', 'done', 'refused', 'cancelled')))");
        DB::statement("alter table data_requests add constraint data_requests_correction_says check (kind <> 'correction' or reason is not null)");
    }

    public function down(): void
    {
        DB::statement("delete from data_requests where kind = 'correction'");
        DB::statement('alter table data_requests drop constraint data_requests_correction_says');
        DB::statement('alter table data_requests drop constraint data_requests_status_kind');
        DB::statement("alter table data_requests add constraint data_requests_status_kind check (
            (kind = 'export' and status in ('pending', 'ready', 'expired', 'failed'))
            or (kind = 'erasure' and status in ('pending', 'approved', 'done', 'refused', 'cancelled')))");
        DB::statement('alter table data_requests drop constraint data_requests_kind');
        DB::statement("alter table data_requests add constraint data_requests_kind check (kind in ('export', 'erasure'))");
        DB::statement('alter table data_requests alter column kind type varchar(8)');
    }
};
