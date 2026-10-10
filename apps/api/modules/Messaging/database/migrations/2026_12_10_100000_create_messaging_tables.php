<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Messages between a teacher and a student on the platform only
 * (docs/architecture/02, section 17): one conversation per teacher and
 * student, the student's guardians always in it. What is written stays as
 * it was sent (never changed or deleted); a phone number, an email, a link
 * or a handle is hidden before anyone reads it, and what was hidden is kept
 * encrypted for the academy's oversight only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The academy's switch (no row: on).
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('messaging_settings');

        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('teacher_id');
            $table->uuid('student_id');                            // the student's guardians are in it through guardian_student
            $table->timestampTz('last_message_at')->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedInteger('hidden_count')->default(0);   // messages something was hidden from
            $table->timestampTz('flagged_at')->nullable();         // the last time something was hidden or an outside app named
            $table->timestampTz('reviewed_at')->nullable();        // the last time someone overseeing read it
            $table->timestampTz('locked_at')->nullable();          // paused by the academy: read only for both sides
            $table->uuid('locked_by')->nullable();
            $table->string('lock_reason', 400)->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'teacher_id', 'student_id']);
            $table->index(['academy_id', 'student_id']);
            $table->index(['academy_id', 'last_message_at']);
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('student_id', 'users');
            $table->tenantForeign('locked_by', 'users');
        });
        DB::statement('alter table conversations add constraint conversations_two check (teacher_id <> student_id)');
        DB::statement('alter table conversations add constraint conversations_lock check ((locked_at is null) = (locked_by is null) and (locked_at is null) = (lock_reason is null))');
        TenantTable::enableRls('conversations');
        DB::statement('revoke delete on conversations from lms_app');

        // What was said, written once.
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('conversation_id');
            $table->uuid('author_id');
            $table->string('side', 8);                             // teacher | student | guardian
            $table->text('body');                                  // as everyone reads it: contacts hidden
            $table->jsonb('hidden')->default('[]');                // what was hidden: phone, email, link, handle
            $table->jsonb('flags')->default('[]');                 // what was named, not hidden: outside_app
            $table->text('original')->nullable();                  // the words as typed, only when something was hidden (encrypted)
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'conversation_id', 'created_at']);
            $table->index(['academy_id', 'author_id', 'created_at']);
            $table->tenantForeign('conversation_id', 'conversations');
            $table->tenantForeign('author_id', 'users');
        });
        DB::statement("alter table conversation_messages add constraint conversation_messages_side check (side in ('teacher', 'student', 'guardian'))");
        DB::statement("alter table conversation_messages add constraint conversation_messages_lists check (jsonb_typeof(hidden) = 'array' and jsonb_typeof(flags) = 'array')");
        DB::statement("alter table conversation_messages add constraint conversation_messages_original check ((original is null) = (hidden = '[]'::jsonb))");
        TenantTable::enableRls('conversation_messages');
        DB::statement('revoke update, delete on conversation_messages from lms_app');

        // How far each member has read.
        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('conversation_id');
            $table->uuid('user_id');
            $table->timestampTz('read_at');

            $table->primary(['academy_id', 'conversation_id', 'user_id']);
            $table->index(['academy_id', 'user_id']);
            $table->tenantForeign('conversation_id', 'conversations');
            $table->tenantForeign('user_id', 'users');
        });
        TenantTable::enableRls('conversation_reads');

        // Live screens: the teacher, the student and each guardian on their own channel; oversight on the academy's.
        DB::statement(<<<'SQL'
            create or replace function lms_conversations_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'messages:' || new.teacher_id);
                perform lms_notify_family('messages', new.academy_id, new.student_id);
                perform pg_notify('lms_live', 'conversations:' || new.academy_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger conversations_live after insert or update on conversations for each row execute function lms_conversations_changed()');
        // Reading on one device clears the count on the others.
        DB::statement(<<<'SQL'
            create or replace function lms_conversation_reads_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'messages:' || new.user_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger conversation_reads_live after insert or update on conversation_reads for each row execute function lms_conversation_reads_changed()');
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reads');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('messaging_settings');
        DB::statement('drop function if exists lms_conversations_changed()');
        DB::statement('drop function if exists lms_conversation_reads_changed()');
    }
};
