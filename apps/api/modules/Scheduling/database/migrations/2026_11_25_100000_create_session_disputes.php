<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Disputes (docs/architecture/01, section 8; 02, section 5): the family or
 * the teacher says a decided lesson's outcome is wrong, within 48 hours of
 * its end. The lesson goes back to "needs review" and the academy decides
 * it again; the dispute keeps what was said, what it was before, and the
 * answer the person reads. One per lesson, whoever raised it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('session_id');
            $table->uuid('raised_by');
            $table->string('side', 8);                      // family | teacher
            $table->string('reason', 24);
            $table->text('note');
            $table->string('outcome_before', 20);           // the lesson's status when disputed
            $table->string('status', 10)->default('open');  // open | upheld | rejected
            $table->string('outcome_after', 20)->nullable();
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();      // the academy's answer, read by whoever raised it
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'session_id']);
            $table->index(['academy_id', 'status', 'created_at']);
            $table->tenantForeign('session_id', 'sessions');
            $table->tenantForeign('raised_by', 'users');
            $table->tenantForeign('decided_by', 'users');
        });
        DB::statement("alter table session_disputes add constraint session_disputes_side check (side in ('family', 'teacher'))");
        DB::statement("alter table session_disputes add constraint session_disputes_status check (status in ('open', 'upheld', 'rejected'))");
        DB::statement("alter table session_disputes add constraint session_disputes_decided check ((status = 'open') = (decided_at is null))");
        TenantTable::enableRls('session_disputes');
        DB::statement('revoke delete on session_disputes from lms_app');

        // A dispute raised or decided: the academy's board and calendar, and the family's and the teacher's lessons, follow it.
        DB::statement(<<<'SQL'
            create or replace function lms_session_disputes_changed() returns trigger language plpgsql as $$
            declare
                teacher uuid;
                student uuid;
            begin
                perform pg_notify('lms_live', 'board:' || new.academy_id);
                select s.teacher_id into teacher from sessions s where s.academy_id = new.academy_id and s.id = new.session_id;
                if teacher is not null then
                    perform pg_notify('lms_live', 'lessons:' || teacher);
                end if;
                select p.user_id into student from session_participants p
                    where p.academy_id = new.academy_id and p.session_id = new.session_id and p.role = 'student' limit 1;
                if student is not null then
                    perform lms_notify_family('lessons', new.academy_id, student);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger session_disputes_live after insert or update on session_disputes for each row execute function lms_session_disputes_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists session_disputes_live on session_disputes');
        DB::statement('drop function if exists lms_session_disputes_changed()');
        Schema::dropIfExists('session_disputes');
    }
};
