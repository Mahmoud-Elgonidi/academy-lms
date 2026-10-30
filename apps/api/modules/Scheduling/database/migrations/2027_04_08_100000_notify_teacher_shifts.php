<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A teacher's hours follow live: a proposal changing step, a message on it,
 * or an approved schedule (one that starts later too) raise "lessons:{teacher}"
 * (the teacher's own signal, which "My hours" in the app and the portal
 * follow beside their lessons). Postgres sends them on commit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_teacher_shifts_changed() returns trigger language plpgsql as $$
            declare
                row_teacher uuid;
            begin
                if tg_table_name = 'shift_proposal_comments' then
                    select teacher_id into row_teacher from shift_proposals where id = new.proposal_id;
                elsif tg_op = 'DELETE' then
                    row_teacher := old.teacher_id;
                else
                    row_teacher := new.teacher_id;
                end if;
                if row_teacher is not null then
                    perform pg_notify('lms_live', 'lessons:' || row_teacher);
                end if;
                return null;
            end
            $$
        SQL);

        DB::statement('create trigger shift_proposals_live after insert or update or delete on shift_proposals for each row execute function lms_teacher_shifts_changed()');
        DB::statement('create trigger shift_proposal_comments_live after insert on shift_proposal_comments for each row execute function lms_teacher_shifts_changed()');
        DB::statement('create trigger teacher_shift_schedules_live after insert or update or delete on teacher_shift_schedules for each row execute function lms_teacher_shifts_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists teacher_shift_schedules_live on teacher_shift_schedules');
        DB::statement('drop trigger if exists shift_proposal_comments_live on shift_proposal_comments');
        DB::statement('drop trigger if exists shift_proposals_live on shift_proposals');
        DB::statement('drop function if exists lms_teacher_shifts_changed()');
    }
};
