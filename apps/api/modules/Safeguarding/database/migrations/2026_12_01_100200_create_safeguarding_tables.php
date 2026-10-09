<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Keeping children safe (docs/architecture/02, section 15): a concern
 * anyone can raise (a child, a guardian, a teacher, or the office writing it
 * down), read only by the academy's safeguarding leads, never by a teacher it
 * may be about. What was said is encrypted by the application; the record
 * and every step taken on it stay (nothing is deleted or rewritten).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safeguarding_concerns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->unsignedInteger('number');                     // the academy's own count: #1, #2 …
            $table->uuid('reporter_id');                           // who raised it (or the office member who wrote it down)
            $table->string('reporter_side', 8);                    // student | guardian | teacher | staff
            $table->uuid('student_id')->nullable();                // the child it is about
            $table->uuid('teacher_id')->nullable();                // a teacher it may be about (from the lesson, or the office's choice)
            $table->uuid('session_id')->nullable();                // the lesson it happened in, if any
            $table->string('category', 20);
            $table->text('body');                                  // what they wrote (encrypted)
            $table->boolean('urgent')->default(false);             // someone may be in danger now
            $table->string('status', 8)->default('new');           // new → open (a lead took it) → closed
            $table->uuid('acknowledged_by')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('escalated_at')->nullable();       // nobody took it within the hour: the leads heard again
            $table->string('outcome', 16)->nullable();
            $table->text('reporter_reply')->nullable();            // what the reporter reads when it closes (encrypted)
            $table->uuid('closed_by')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'number']);
            $table->index(['academy_id', 'status', 'created_at']);
            $table->index(['academy_id', 'reporter_id']);
            $table->tenantForeign('reporter_id', 'users');
            $table->tenantForeign('student_id', 'users');
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('session_id', 'sessions');
            $table->tenantForeign('acknowledged_by', 'users');
            $table->tenantForeign('closed_by', 'users');
        });
        DB::statement("alter table safeguarding_concerns add constraint safeguarding_concerns_side check (reporter_side in ('student', 'guardian', 'teacher', 'staff'))");
        DB::statement("alter table safeguarding_concerns add constraint safeguarding_concerns_category check (category in ('adult_behaviour', 'outside_contact', 'content', 'bullying', 'wellbeing', 'other'))");
        DB::statement("alter table safeguarding_concerns add constraint safeguarding_concerns_status check (status in ('new', 'open', 'closed'))");
        DB::statement("alter table safeguarding_concerns add constraint safeguarding_concerns_outcome check (outcome is null or outcome in ('no_concern', 'resolved', 'teacher_action', 'referred', 'duplicate'))");
        DB::statement("alter table safeguarding_concerns add constraint safeguarding_concerns_closed check ((status = 'closed') = (outcome is not null and closed_at is not null))");
        DB::statement("alter table safeguarding_concerns add constraint safeguarding_concerns_taken check (status = 'new' or acknowledged_at is not null)");
        TenantTable::enableRls('safeguarding_concerns');
        DB::statement('revoke delete on safeguarding_concerns from lms_app');

        // Every step on a concern (a lead's note, taking it, a pause, closing, reopening): written once, never changed.
        Schema::create('concern_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('concern_id');
            $table->uuid('author_id')->nullable();                 // null: the platform (an escalation)
            $table->string('kind', 6);                              // note | event
            $table->string('event', 20)->nullable();               // reported, acknowledged, escalated, teacher_paused, teacher_resumed, closed, reopened
            $table->text('body')->nullable();                      // a note's words (encrypted)
            $table->jsonb('data')->default('{}');
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'concern_id', 'created_at']);
            $table->tenantForeign('concern_id', 'safeguarding_concerns')->cascadeOnDelete();
            $table->tenantForeign('author_id', 'users');
        });
        DB::statement("alter table concern_entries add constraint concern_entries_kind check (kind in ('note', 'event'))");
        DB::statement("alter table concern_entries add constraint concern_entries_shape check ((kind = 'note' and body is not null) or (kind = 'event' and event is not null))");
        TenantTable::enableRls('concern_entries');
        DB::statement('revoke update, delete on concern_entries from lms_app');

        // The leads' screens and the reporter's own list hear it change.
        DB::statement(<<<'SQL'
            create or replace function lms_safeguarding_concerns_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'safeguarding:' || new.academy_id);
                perform pg_notify('lms_live', 'concerns:' || new.reporter_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger safeguarding_concerns_live after insert or update on safeguarding_concerns for each row execute function lms_safeguarding_concerns_changed()');
        DB::statement(<<<'SQL'
            create or replace function lms_concern_entries_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'safeguarding:' || new.academy_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger concern_entries_live after insert on concern_entries for each row execute function lms_concern_entries_changed()');
    }

    public function down(): void
    {
        Schema::dropIfExists('concern_entries');
        Schema::dropIfExists('safeguarding_concerns');
        DB::statement('drop function if exists lms_safeguarding_concerns_changed()');
        DB::statement('drop function if exists lms_concern_entries_changed()');
    }
};
