<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A signal for a learner's family: lms_notify_family('lessons', academy,
 * student) raises "lessons:{student}" and the same for each of the student's
 * guardians on the live channel (Modules\SharedKernel\Realtime\LiveSignals),
 * so their open pages refresh. Other modules' triggers call it; who the
 * guardians are stays People's.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            create or replace function lms_notify_family(topic text, academy uuid, student uuid) returns void language plpgsql as $$
            declare
                guardian uuid;
            begin
                if student is null then
                    return;
                end if;
                perform pg_notify('lms_live', topic || ':' || student);
                for guardian in select guardian_id from guardian_student where academy_id = academy and student_id = student loop
                    perform pg_notify('lms_live', topic || ':' || guardian);
                end loop;
            end
            $$
        SQL);
    }

    public function down(): void
    {
        DB::statement('drop function if exists lms_notify_family(text, uuid, uuid)');
    }
};
