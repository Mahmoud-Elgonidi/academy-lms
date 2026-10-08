<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Lesson recordings with consent (docs/architecture/01, section 9; 03,
 * "التسجيلات"): the academy's choice (off, the teacher starts it, or every
 * lesson whose people agreed), each person's consent (a teacher or an adult
 * student for themself, a guardian for a child, or the office from paper),
 * the recordings the video provider made (kept 30 days unless held), and
 * every time someone opened one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_settings', function (Blueprint $table) {
            $table->string('recording', 10)->default('off');           // off | manual (the teacher starts it) | auto (every lesson whose people agreed)
            $table->string('recording_media', 10)->default('by_age');  // by_age (video when a child is in it, else sound) | video | audio
            $table->unsignedSmallInteger('recording_days')->default(30);
            $table->boolean('recording_family')->default(true);       // a family watches its own lessons
            $table->boolean('recording_teacher')->default(true);      // a teacher watches their own lessons (never downloads)
        });
        DB::statement("alter table classroom_settings add constraint classroom_settings_recording check (recording in ('off', 'manual', 'auto') and recording_media in ('by_age', 'video', 'audio') and recording_days between 7 and 365)");

        Schema::create('recording_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('person_id');                       // who is recorded: a student or a teacher
            $table->string('role', 10);                      // student | teacher
            $table->uuid('given_by')->nullable();            // who agreed: the person, or a child's guardian; null when the office has no account for them
            $table->string('via', 10);                       // self | guardian | staff (from a signed form or the teacher's contract)
            $table->uuid('recorded_by')->nullable();         // the office: who recorded it
            $table->string('note', 300)->nullable();         // the office: what it has
            $table->unsignedSmallInteger('version');         // the wording agreed to (classroom::recordings.consent)
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestampTz('granted_at');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->uuid('withdrawn_by')->nullable();
            $table->string('withdraw_note', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'person_id']);
            $table->tenantForeign('person_id', 'users')->cascadeOnDelete();
            $table->tenantForeign('given_by', 'users');
        });
        DB::statement("alter table recording_consents add constraint recording_consents_shape check (role in ('student', 'teacher') and via in ('self', 'guardian', 'staff')
            and (via = 'staff') = (recorded_by is not null) and (via <> 'staff' or note is not null) and (via = 'staff' or given_by is not null))");
        // One consent stands for a person at a time; agreeing again after taking it back is a new row.
        DB::statement('create unique index recording_consents_one_live on recording_consents (academy_id, person_id) where withdrawn_at is null');
        TenantTable::enableRls('recording_consents');
        DB::statement('revoke delete on recording_consents from lms_app');
        DB::statement(<<<'SQL'
            create or replace function lms_recording_consents_keep() returns trigger language plpgsql as $$
            begin
                if old.withdrawn_at is not null
                    or (to_jsonb(new) - array['withdrawn_at', 'withdrawn_by', 'withdraw_note']) <> (to_jsonb(old) - array['withdrawn_at', 'withdrawn_by', 'withdraw_note'])
                    or new.withdrawn_at is null then
                    raise exception 'recording_consents keeps what was agreed; it can only be withdrawn, once';
                end if;
                return new;
            end
            $$
        SQL);
        DB::statement('create trigger recording_consents_keep before update on recording_consents for each row execute function lms_recording_consents_keep()');
        // A student's family hears it (the privacy page changes); a teacher's own pages too.
        DB::statement(<<<'SQL'
            create or replace function lms_recording_consents_changed() returns trigger language plpgsql as $$
            begin
                if new.role = 'student' then
                    perform lms_notify_family('consents', new.academy_id, new.person_id);
                else
                    perform pg_notify('lms_live', 'lessons:' || new.person_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger recording_consents_live after insert or update on recording_consents for each row execute function lms_recording_consents_changed()');

        Schema::create('recordings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('room_id');
            $table->string('provider', 10);
            $table->string('egress_id', 80)->nullable();    // the provider's job
            $table->string('media', 5);                     // video | audio
            $table->string('status', 10)->default('starting');  // starting | recording | ending | ready | failed | deleted
            $table->uuid('started_by')->nullable();         // null: started by itself (the academy records every lesson)
            $table->uuid('stopped_by')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->string('disk', 20);
            $table->string('path', 300);                    // academies/{academy}/recordings/{session}/{id}.{mp4|ogg}
            $table->string('mime', 40);
            $table->timestampTz('expires_at')->nullable();  // when the file goes (the academy's days after the end)
            $table->timestampTz('held_at')->nullable();     // kept past that, for a reason (a dispute)
            $table->uuid('held_by')->nullable();
            $table->string('hold_reason', 300)->nullable();
            $table->timestampTz('deleted_at')->nullable();  // the file is gone; the row stays
            $table->uuid('deleted_by')->nullable();         // null: its days were up
            $table->string('delete_reason', 300)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'session_id']);
            $table->index(['academy_id', 'status', 'expires_at']);
            $table->unique(['academy_id', 'egress_id']);
            $table->tenantForeign('session_id', 'sessions');
            $table->tenantForeign('room_id', 'rooms');
            $table->tenantForeign('started_by', 'users');
            $table->tenantForeign('stopped_by', 'users');
            $table->tenantForeign('held_by', 'users');
            $table->tenantForeign('deleted_by', 'users');
        });
        DB::statement("alter table recordings add constraint recordings_shape check (media in ('video', 'audio') and status in ('starting', 'recording', 'ending', 'ready', 'failed', 'deleted')
            and (status = 'deleted') = (deleted_at is not null) and (held_at is null) = (hold_reason is null))");
        // One recording runs in a lesson at a time.
        DB::statement("create unique index recordings_one_running on recordings (academy_id, session_id) where status in ('starting', 'recording', 'ending')");
        TenantTable::enableRls('recordings');
        DB::statement('revoke delete on recordings from lms_app');
        // The lesson's pages hear it: the teacher's, and the student's family's (a recording to watch).
        DB::statement(<<<'SQL'
            create or replace function lms_recordings_changed() returns trigger language plpgsql as $$
            declare
                teacher uuid;
                student uuid;
            begin
                if tg_op = 'UPDATE' and old.status = new.status and old.held_at is not distinct from new.held_at then
                    return null;
                end if;
                select s.teacher_id into teacher from sessions s where s.academy_id = new.academy_id and s.id = new.session_id;
                if teacher is not null then
                    perform pg_notify('lms_live', 'lessons:' || teacher);
                end if;
                for student in select user_id from session_participants where academy_id = new.academy_id and session_id = new.session_id and role = 'student' loop
                    perform lms_notify_family('lessons', new.academy_id, student);
                end loop;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger recordings_live after insert or update on recordings for each row execute function lms_recordings_changed()');

        Schema::create('recording_access_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('recording_id');
            $table->uuid('user_id');
            $table->string('as', 10);                       // staff | teacher | family
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestampTz('opened_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'recording_id', 'opened_at']);
            $table->tenantForeign('recording_id', 'recordings');
            $table->tenantForeign('user_id', 'users');
        });
        DB::statement("alter table recording_access_logs add constraint recording_access_logs_as check (\"as\" in ('staff', 'teacher', 'family'))");
        TenantTable::enableRls('recording_access_logs');
        // Who watched is kept as it happened.
        DB::statement('revoke update, delete on recording_access_logs from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_access_logs');
        DB::statement('drop trigger if exists recordings_live on recordings');
        DB::statement('drop function if exists lms_recordings_changed()');
        Schema::dropIfExists('recordings');
        DB::statement('drop trigger if exists recording_consents_live on recording_consents');
        DB::statement('drop function if exists lms_recording_consents_changed()');
        DB::statement('drop trigger if exists recording_consents_keep on recording_consents');
        DB::statement('drop function if exists lms_recording_consents_keep()');
        Schema::dropIfExists('recording_consents');
        DB::statement('alter table classroom_settings drop constraint if exists classroom_settings_recording');
        Schema::table('classroom_settings', function (Blueprint $table) {
            $table->dropColumn(['recording', 'recording_media', 'recording_days', 'recording_family', 'recording_teacher']);
        });
    }
};
