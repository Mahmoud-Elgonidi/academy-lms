<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The teacher's help with recited homework (docs/architecture/02, section 22):
 * homework may say what is to be recited (expected: a passage of the Quran by
 * surah and verses, as a lesson's text_source), and each recording handed in
 * for it gets one check, word by word, made in the background and shown to
 * the teacher alone (never the family). A check never marks the homework:
 * no grade, status, stars or KPI reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework', function (Blueprint $table) {
            $table->jsonb('expected')->nullable();                  // {kind: quran, surah, from, to}: what is to be recited
        });

        Schema::create('submission_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('submission_id');
            $table->uuid('homework_id');
            $table->string('status', 12);                           // pending | ready | failed
            $table->string('reason', 20)->nullable();               // why it failed (AiFailed's reason)
            $table->string('engine', 80)->nullable();               // what heard it ("words:{model}")
            $table->text('heard')->nullable();                      // the words heard, for the teacher
            $table->jsonb('result')->nullable();                    // {verses, extra, notes, summary}
            $table->decimal('seconds', 8, 2)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('checked_at')->nullable();
            $table->tenantKey();
            $table->unique(['academy_id', 'submission_id']);
            $table->index(['academy_id', 'homework_id']);
            $table->tenantForeign('submission_id', 'homework_submissions')->cascadeOnDelete();
            $table->tenantForeign('homework_id', 'homework')->cascadeOnDelete();
        });
        DB::statement("alter table submission_checks add constraint submission_checks_status check (status in ('pending', 'ready', 'failed'))");
        TenantTable::enableRls('submission_checks');

        // A check made (or failed) refreshes the teacher's homework to mark; the family hears nothing of it.
        DB::statement(<<<'SQL'
            create or replace function lms_submission_check_live() returns trigger language plpgsql as $$
            declare
                teacher uuid;
            begin
                select teacher_id into teacher from homework where academy_id = new.academy_id and id = new.homework_id;
                if teacher is not null then
                    perform pg_notify('lms_live', 'homework:' || teacher);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger submission_checks_live after insert or update on submission_checks for each row execute function lms_submission_check_live()');
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_checks');
        DB::statement('drop function if exists lms_submission_check_live()');
        Schema::table('homework', fn (Blueprint $table) => $table->dropColumn('expected'));
    }
};
