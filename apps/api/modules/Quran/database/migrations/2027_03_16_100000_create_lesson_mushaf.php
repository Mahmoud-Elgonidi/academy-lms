<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The shared mushaf of a Quran lesson and the mistakes notebook (docs/
 * architecture/02, section 22): the page the teacher has open in a lesson
 * (the student's follows it, inside the room or beside an outside meeting),
 * and each word the teacher marked in it (wrong, hesitation, tajweed), one
 * mark a word a lesson. A word is its surah, its verse and its place in the
 * verse from 1, as the platform's copy counts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_mushaf', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->unsignedSmallInteger('page');
            $table->timestampsTz();
            $table->tenantKey();
            $table->unique(['academy_id', 'session_id']);
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
        });
        DB::statement('alter table lesson_mushaf add constraint lesson_mushaf_page check (page between 1 and 604)');
        TenantTable::enableRls('lesson_mushaf');

        Schema::create('recitation_mistakes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('session_id');
            $table->uuid('teacher_id');
            $table->unsignedSmallInteger('surah');
            $table->unsignedSmallInteger('verse');
            $table->unsignedSmallInteger('word');                   // its place in the verse, from 1
            $table->string('kind', 12);                             // wrong | hesitation | tajweed
            $table->timestampsTz();
            $table->tenantKey();
            $table->unique(['academy_id', 'session_id', 'surah', 'verse', 'word']);
            $table->index(['academy_id', 'student_id', 'surah', 'verse']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('session_id', 'sessions')->cascadeOnDelete();
            $table->tenantForeign('teacher_id', 'users');
        });
        DB::statement("alter table recitation_mistakes add constraint recitation_mistakes_kind check (kind in ('wrong', 'hesitation', 'tajweed'))");
        DB::statement('alter table recitation_mistakes add constraint recitation_mistakes_word check (surah between 1 and 114 and verse >= 1 and word >= 1)');
        TenantTable::enableRls('recitation_mistakes');

        // A page turned or a word marked: the lesson's open screens follow (its own channel for the class page,
        // the people's own channels for the apps and the portal).
        DB::statement(<<<'SQL'
            create or replace function lms_mushaf_live() returns trigger language plpgsql as $$
            declare
                row record;
                lesson record;
            begin
                if tg_op = 'DELETE' then
                    row := old;
                else
                    row := new;
                end if;
                select s.id, s.teacher_id, p.user_id as student_id into lesson
                    from sessions s left join session_participants p on p.academy_id = s.academy_id and p.session_id = s.id and p.role = 'student'
                    where s.academy_id = row.academy_id and s.id = row.session_id limit 1;
                if lesson.id is null then
                    return null;
                end if;
                perform pg_notify('lms_live', 'lesson-mushaf:' || lesson.id);
                perform pg_notify('lms_live', 'mushaf:' || lesson.teacher_id);
                perform lms_notify_family('mushaf', row.academy_id, lesson.student_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger lesson_mushaf_live after insert or update on lesson_mushaf for each row execute function lms_mushaf_live()');
        DB::statement('create trigger recitation_mistakes_live after insert or update or delete on recitation_mistakes for each row execute function lms_mushaf_live()');
    }

    public function down(): void
    {
        Schema::dropIfExists('recitation_mistakes');
        Schema::dropIfExists('lesson_mushaf');
        DB::statement('drop function if exists lms_mushaf_live()');
    }
};
