<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Group lessons (docs/architecture/02, "الحصص الجماعية"): a dispute is about
 * one student's seat. A one-to-one lesson's dispute is about its student, so
 * the rows already there take the lesson's student; the one-per-lesson key
 * becomes one per lesson and student, and the family that hears of it is
 * the dispute's student's. A dispute whose student cannot be found keeps
 * none, and stays the lesson's only one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table session_disputes add column student_id uuid null');
        DB::statement(<<<'SQL'
            update session_disputes d set student_id = (
                select p.user_id from session_participants p
                 where p.academy_id = d.academy_id and p.session_id = d.session_id and p.role = 'student'
                 order by p.user_id limit 1)
        SQL);
        // A lesson whose student row is gone (an old cancelled lesson): its course's student.
        DB::statement(<<<'SQL'
            update session_disputes d set student_id = e.student_id
              from sessions s join enrollments e on e.academy_id = s.academy_id and e.id = s.enrollment_id
             where d.student_id is null and s.academy_id = d.academy_id and s.id = d.session_id
        SQL);
        // Anything still without one stays a lesson's own dispute (null, once per lesson: nulls not distinct);
        // every new dispute names its student (DisputeLesson).
        DB::statement('alter table session_disputes add constraint session_disputes_student_fk foreign key (academy_id, student_id) references users (academy_id, id)');
        DB::statement('alter table session_disputes drop constraint if exists session_disputes_academy_id_session_id_unique');
        DB::statement('create unique index session_disputes_one_per_seat on session_disputes (academy_id, session_id, student_id) nulls not distinct');

        DB::statement(<<<'SQL'
            create or replace function lms_session_disputes_changed() returns trigger language plpgsql as $$
            declare
                teacher uuid;
            begin
                perform pg_notify('lms_live', 'board:' || new.academy_id);
                select s.teacher_id into teacher from sessions s where s.academy_id = new.academy_id and s.id = new.session_id;
                if teacher is not null then
                    perform pg_notify('lms_live', 'lessons:' || teacher);
                end if;
                if new.student_id is not null then
                    perform lms_notify_family('lessons', new.academy_id, new.student_id);
                end if;
                return null;
            end
            $$
        SQL);
    }

    public function down(): void
    {
        DB::statement('drop index if exists session_disputes_one_per_seat');
        DB::statement('alter table session_disputes add constraint session_disputes_academy_id_session_id_unique unique (academy_id, session_id)');
        DB::statement('alter table session_disputes drop constraint if exists session_disputes_student_fk');
        DB::statement('alter table session_disputes drop column if exists student_id');
    }
};
