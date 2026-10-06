<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Live screens beyond the board. Changes now go out on the one channel the
 * relay listens to (`lms_live`, Modules\SharedKernel\Realtime\LiveSignals) as
 * "{topic}:{id}":
 *   - every write to a lesson, its participants or its log: "board:{academy}";
 *   - a lesson's time, status or teacher: also "lessons:{teacher}" (both
 *     teachers when it moves), for the teacher's "today" page.
 * Postgres sends them on commit, and folds repeats in one transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_board_changed() returns trigger language plpgsql as $$
            begin
                if tg_op = 'DELETE' then
                    perform pg_notify('lms_live', 'board:' || old.academy_id);
                else
                    perform pg_notify('lms_live', 'board:' || new.academy_id);
                end if;
                return null;
            end
            $$
        SQL);

        DB::statement(<<<'SQL'
            create or replace function lms_sessions_changed() returns trigger language plpgsql as $$
            begin
                if tg_op = 'DELETE' then
                    perform pg_notify('lms_live', 'board:' || old.academy_id);
                    perform pg_notify('lms_live', 'lessons:' || old.teacher_id);
                    return null;
                end if;
                perform pg_notify('lms_live', 'board:' || new.academy_id);
                perform pg_notify('lms_live', 'lessons:' || new.teacher_id);
                if tg_op = 'UPDATE' then
                    if old.teacher_id <> new.teacher_id then
                        perform pg_notify('lms_live', 'lessons:' || old.teacher_id);
                    end if;
                end if;
                return null;
            end
            $$
        SQL);

        DB::statement('drop trigger if exists sessions_board_changed on sessions');
        DB::statement('create trigger sessions_live_changed after insert or update or delete on sessions for each row execute function lms_sessions_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists sessions_live_changed on sessions');
        DB::statement('drop function if exists lms_sessions_changed()');
        DB::statement(<<<'SQL'
            create or replace function lms_board_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_board', (case when tg_op = 'DELETE' then old.academy_id else new.academy_id end)::text);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger sessions_board_changed after insert or update or delete on sessions for each row execute function lms_board_changed()');
    }
};
