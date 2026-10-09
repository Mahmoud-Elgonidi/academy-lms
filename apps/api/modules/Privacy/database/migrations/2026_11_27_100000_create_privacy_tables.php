<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * People's rights over their data (docs/architecture/03, section 11): a
 * copy of everything the academy holds about them, and erasure. A copy is
 * built on its own and kept a week; an erasure waits for the academy, which
 * may refuse it with a reason (money still due, lessons still to come) and
 * otherwise anonymises the account a day after approving it. Each academy
 * also says who it is on its privacy page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('kind', 8);                       // export | erasure
            $table->uuid('subject_id');                      // whose data
            $table->uuid('requested_by');                    // the person, their guardian, or someone at the academy for them
            $table->string('status', 10)->default('pending');
            // export: pending → ready → expired (or failed); erasure: pending → approved → done, or refused / cancelled
            $table->text('reason')->nullable();              // the requester's words (an erasure)
            $table->text('answer')->nullable();              // the academy's reason, when it refuses
            $table->timestampTz('due_at')->nullable();       // an erasure is answered within 30 days
            $table->timestampTz('erase_after')->nullable();  // approved: the account goes then (a day later, time for the last messages)
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('done_at')->nullable();
            $table->string('file_path')->nullable();         // the copy, on the private disk
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestampTz('expires_at')->nullable();   // the copy is there until then
            $table->jsonb('summary')->default('{}');         // what a copy holds, or what an erasure removed, by part
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'status', 'created_at']);
            $table->index(['academy_id', 'subject_id', 'created_at']);
            $table->tenantForeign('subject_id', 'users');
            $table->tenantForeign('requested_by', 'users');
            $table->tenantForeign('decided_by', 'users');
        });
        DB::statement("alter table data_requests add constraint data_requests_kind check (kind in ('export', 'erasure'))");
        DB::statement("alter table data_requests add constraint data_requests_status check (status in ('pending', 'ready', 'expired', 'failed', 'approved', 'done', 'refused', 'cancelled'))");
        DB::statement("alter table data_requests add constraint data_requests_status_kind check (
            (kind = 'export' and status in ('pending', 'ready', 'expired', 'failed'))
            or (kind = 'erasure' and status in ('pending', 'approved', 'done', 'refused', 'cancelled')))");
        DB::statement("alter table data_requests add constraint data_requests_refused check (status <> 'refused' or answer is not null)");
        // One open request of a kind per person: asked twice, it is the same request.
        DB::statement("create unique index data_requests_open on data_requests (academy_id, subject_id, kind) where status in ('pending', 'approved')");
        TenantTable::enableRls('data_requests');
        // Whoever asked and whom it is about see it change (a copy ready, an answer); the academy's page too.
        DB::statement(<<<'SQL'
            create or replace function lms_data_requests_changed() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'privacy:' || new.requested_by);
                if new.subject_id <> new.requested_by then
                    perform pg_notify('lms_live', 'privacy:' || new.subject_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger data_requests_live after insert or update on data_requests for each row execute function lms_data_requests_changed()');

        Schema::create('privacy_settings', function (Blueprint $table) {
            $table->tenant();
            $table->string('legal_name', 200)->nullable();    // the academy as the law knows it (the data's controller)
            $table->string('contact_email', 200)->nullable(); // where people write about their data
            $table->string('address', 300)->nullable();
            $table->text('privacy_extra')->nullable();        // the academy's own words added to the privacy notice
            $table->text('terms_extra')->nullable();          // and to the terms
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->primary('academy_id');
            $table->tenantForeign('updated_by', 'users');
        });
        TenantTable::enableRls('privacy_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_settings');
        Schema::dropIfExists('data_requests');
        DB::statement('drop function if exists lms_data_requests_changed()');
    }
};
