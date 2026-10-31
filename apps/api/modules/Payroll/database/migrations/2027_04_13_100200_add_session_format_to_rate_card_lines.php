<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): a teacher's group
 * lesson is paid a fixed group rate, one line per lesson whatever the number
 * of students. A rate card line is for private lessons (as every line was)
 * or for group ones; a group lesson with no group line is paid as private.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("alter table rate_card_lines add column session_format varchar(10) not null default 'private'");
        DB::statement("alter table rate_card_lines add constraint rate_card_lines_format check (session_format in ('private', 'group'))");
        DB::statement('drop index if exists rate_card_lines_once');
        DB::statement('create unique index rate_card_lines_once on rate_card_lines (academy_id, rate_card_id, subject_id, session_type, session_format) nulls not distinct');
    }

    public function down(): void
    {
        DB::statement("delete from rate_card_lines where session_format = 'group'");
        DB::statement('drop index if exists rate_card_lines_once');
        DB::statement('create unique index rate_card_lines_once on rate_card_lines (academy_id, rate_card_id, subject_id, session_type) nulls not distinct');
        DB::statement('alter table rate_card_lines drop constraint if exists rate_card_lines_format');
        DB::statement('alter table rate_card_lines drop column if exists session_format');
    }
};
