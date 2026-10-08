<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Support tickets and complaints (docs/architecture/01, section 16; 02,
 * section 12): a family or a teacher writes to the academy about a lesson,
 * an invoice, a technical problem or anything else; the academy answers
 * within its targets (SLA) by priority, and a complaint about a teacher, once
 * the academy confirms it, counts in the teacher's numbers. What was said
 * is kept as it was: a ticket's messages are never changed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_settings', function (Blueprint $table) {
            $table->tenant();
            // {priority: {respond: hours, resolve: hours}}: the first answer and the solution, from when the ticket came in
            $table->jsonb('targets');
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        DB::statement("alter table support_settings add constraint support_settings_targets check (jsonb_typeof(targets) = 'object')");
        TenantTable::enableRls('support_settings');

        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->unsignedInteger('number');                  // the academy's own running number, what people quote
            $table->string('kind', 10);                         // complaint | billing | technical | other
            $table->string('priority', 8);                      // low | normal | high | urgent
            $table->string('status', 10)->default('open');      // open (the academy's turn) | waiting (the requester's) | resolved | closed
            $table->string('subject', 150);
            $table->uuid('requester_id');                       // who the academy answers: a guardian, a student or a teacher
            $table->string('side', 8);                          // family | teacher: the requester's side
            $table->uuid('student_id')->nullable();             // the learner it is about (family tickets)
            $table->uuid('session_id')->nullable();             // the lesson it is about
            $table->uuid('teacher_id')->nullable();             // the teacher it is about (a complaint; the lesson's teacher by default)
            $table->uuid('opened_by');                          // the requester, or someone at the academy writing it down for them
            $table->uuid('assignee_id')->nullable();
            $table->timestampTz('respond_by');                  // the first answer's target
            $table->timestampTz('resolve_by');                  // the solution's target
            $table->timestampTz('first_response_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('last_message_at');
            $table->string('last_side', 9);                     // requester | staff: who wrote last
            $table->timestampTz('overdue_alerted_at')->nullable();
            // A complaint: waiting for a decision, confirmed (counts against the teacher) or dismissed.
            $table->string('complaint_status', 10)->nullable(); // pending | validated | dismissed
            $table->uuid('complaint_decided_by')->nullable();
            $table->timestampTz('complaint_decided_at')->nullable();
            $table->string('complaint_note', 500)->nullable(); // the academy's own reason; never shown to the family
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'number']);
            $table->index(['academy_id', 'status', 'last_message_at']);
            $table->index(['academy_id', 'requester_id', 'last_message_at']);
            $table->index(['academy_id', 'assignee_id', 'status']);
            $table->index(['academy_id', 'teacher_id', 'complaint_status']);
            $table->tenantForeign('requester_id', 'users');
            $table->tenantForeign('student_id', 'users');
            $table->tenantForeign('session_id', 'sessions');
            $table->tenantForeign('teacher_id', 'users');
            $table->tenantForeign('opened_by', 'users');
            $table->tenantForeign('assignee_id', 'users');
            $table->tenantForeign('complaint_decided_by', 'users');
        });
        DB::statement("alter table tickets add constraint tickets_kind check (kind in ('complaint', 'billing', 'technical', 'other'))");
        DB::statement("alter table tickets add constraint tickets_priority check (priority in ('low', 'normal', 'high', 'urgent'))");
        DB::statement("alter table tickets add constraint tickets_status check (status in ('open', 'waiting', 'resolved', 'closed'))");
        DB::statement("alter table tickets add constraint tickets_side check (side in ('family', 'teacher'))");
        DB::statement("alter table tickets add constraint tickets_last_side check (last_side in ('requester', 'staff'))");
        DB::statement("alter table tickets add constraint tickets_complaint check ((kind = 'complaint') = (complaint_status is not null) and (complaint_status is distinct from 'validated' or teacher_id is not null))");
        DB::statement("alter table tickets add constraint tickets_complaint_decided check ((complaint_status in ('validated', 'dismissed')) = (complaint_decided_at is not null))");
        DB::statement("alter table tickets add constraint tickets_resolved check ((status in ('resolved', 'closed')) = (resolved_at is not null) and (status = 'closed') = (closed_at is not null))");
        TenantTable::enableRls('tickets');
        DB::statement('revoke delete on tickets from lms_app');

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('ticket_id');
            $table->uuid('author_id')->nullable();              // null: the system (closed after a quiet week)
            $table->string('side', 9);                          // requester | staff | system
            $table->string('kind', 8);                          // message | note (the academy's own) | event (what changed)
            $table->text('body')->nullable();
            $table->string('event', 20)->nullable();            // assigned | kind | priority | resolved | reopened | closed | complaint
            $table->jsonb('data')->default('{}');
            $table->string('file_path')->nullable();            // one picture or PDF, on the private disk
            $table->string('file_name', 190)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->tenantKey();
            $table->index(['academy_id', 'ticket_id', 'created_at']);
            $table->tenantForeign('ticket_id', 'tickets');
            $table->tenantForeign('author_id', 'users');
        });
        DB::statement("alter table ticket_messages add constraint ticket_messages_side check (side in ('requester', 'staff', 'system'))");
        DB::statement("alter table ticket_messages add constraint ticket_messages_kind check (kind in ('message', 'note', 'event'))");
        DB::statement("alter table ticket_messages add constraint ticket_messages_shape check ((kind = 'event') = (event is not null) and (kind = 'event' or body is not null or file_path is not null) and (kind <> 'note' or side = 'staff'))");
        DB::statement('alter table ticket_messages add constraint ticket_messages_file check ((file_path is null) = (file_name is null) and (file_path is null) = (size is null))');
        TenantTable::enableRls('ticket_messages');
        // What was said stays as it was.
        DB::statement('revoke update, delete on ticket_messages from lms_app');

        // The academy's open queue hears every ticket that changes; the requester's own screens hear theirs.
        DB::statement(<<<'SQL'
            create or replace function lms_tickets_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'support:' || new.academy_id);
                perform pg_notify('lms_live', 'tickets:' || new.requester_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger tickets_live after insert or update on tickets for each row execute function lms_tickets_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists tickets_live on tickets');
        DB::statement('drop function if exists lms_tickets_changed()');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('support_settings');
    }
};
