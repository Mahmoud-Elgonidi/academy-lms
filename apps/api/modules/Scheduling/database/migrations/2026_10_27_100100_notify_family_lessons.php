<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A lesson's student and their guardians hear "lessons:{person}" too, not
 * only its teacher: when its time, status or teacher changes (the sessions
 * trigger, for each student in it) and when a student is booked into it or
 * taken out (session_participants; attendance columns raise nothing, so a
 * lesson in progress does not keep refreshing the family's pages).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_sessions_changed() returns trigger language plpgsql as $$
            declare
                student uuid;
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
                    for student in select user_id from session_participants where academy_id = new.academy_id and session_id = new.id and role = 'student' loop
                        perform lms_notify_family('lessons', new.academy_id, student);
                    end loop;
                end if;
                return null;
            end
            $$
        SQL);

        DB::statement(<<<'SQL'
            create or replace function lms_session_participants_changed() returns trigger language plpgsql as $$
            begin
                if tg_op in ('UPDATE', 'DELETE') and old.role = 'student' then
                    perform lms_notify_family('lessons', old.academy_id, old.user_id);
                end if;
                if tg_op in ('INSERT', 'UPDATE') and new.role = 'student' then
                    perform lms_notify_family('lessons', new.academy_id, new.user_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger session_participants_live_changed after insert or delete or update of user_id, role, is_active on session_participants for each row execute function lms_session_participants_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists session_participants_live_changed on session_participants');
        DB::statement('drop function if exists lms_session_participants_changed()');
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
    }
};
