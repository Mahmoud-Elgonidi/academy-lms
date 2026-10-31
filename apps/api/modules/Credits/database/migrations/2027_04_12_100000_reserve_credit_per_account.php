<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): one lesson, a
 * reservation for each student in it, each from their own account. The key
 * becomes (academy, session, account); a one-to-one lesson keeps its one
 * row, so the rows already there stay as they are. A makeup lot may be kept
 * for one kind of makeup lesson: private (with the course's teacher) or
 * group (a free seat in another group's lesson), or either when null.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            do $$
            declare pk text;
            begin
                select conname into pk from pg_constraint where conrelid = 'credit_reservations'::regclass and contype = 'p';
                execute format('alter table credit_reservations drop constraint %I', pk);
            end $$
        SQL);
        DB::statement('alter table credit_reservations add constraint credit_reservations_pkey primary key (academy_id, session_id, account_id)');

        DB::statement('alter table credit_lots add column use_for varchar(10) null');
        DB::statement("alter table credit_lots add constraint credit_lots_use_for check (use_for is null or (use_for in ('private', 'group') and source = 'makeup'))");
    }

    public function down(): void
    {
        DB::statement('alter table credit_lots drop constraint if exists credit_lots_use_for');
        DB::statement('alter table credit_lots drop column if exists use_for');
        DB::statement('alter table credit_reservations drop constraint credit_reservations_pkey');
        DB::statement('alter table credit_reservations add constraint credit_reservations_pkey primary key (academy_id, session_id)');
    }
};
