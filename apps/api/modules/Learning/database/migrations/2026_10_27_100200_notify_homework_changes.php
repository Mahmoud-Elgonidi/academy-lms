<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Homework pages refresh when homework changes: given, handed in (every
 * hand-in, also a second one), marked or removed raises "homework:{person}"
 * for its teacher, its student and the student's guardians on the live
 * channel (Modules\SharedKernel\Realtime\LiveSignals), sent on commit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_homework_changed() returns trigger language plpgsql as $$
            declare
                hw homework;
            begin
                if tg_table_name = 'homework' then
                    hw := case when tg_op = 'DELETE' then old else new end;
                else
                    select * into hw from homework
                    where academy_id = (case when tg_op = 'DELETE' then old.academy_id else new.academy_id end)
                      and id = (case when tg_op = 'DELETE' then old.homework_id else new.homework_id end);
                    if not found then
                        return null;
                    end if;
                end if;
                perform pg_notify('lms_live', 'homework:' || hw.teacher_id);
                perform lms_notify_family('homework', hw.academy_id, hw.student_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger homework_live_changed after insert or update or delete on homework for each row execute function lms_homework_changed()');
        DB::statement('create trigger homework_submissions_live_changed after insert or delete on homework_submissions for each row execute function lms_homework_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists homework_submissions_live_changed on homework_submissions');
        DB::statement('drop trigger if exists homework_live_changed on homework');
        DB::statement('drop function if exists lms_homework_changed()');
    }
};
