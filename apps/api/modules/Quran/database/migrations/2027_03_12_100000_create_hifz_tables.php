<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The hifz map and its reviews (docs/architecture/02, section 22): a learner's
 * pages of the Madinah mushaf, each learning or memorized, with when it is
 * next due for review (Domain\Hifz\Scheduler, spaced repetition). Every
 * review is kept once (its source and id held unique: a report, a check, a
 * page recited, the teacher), and a page recited for the daily review is
 * checked like homework, its recording deleted once heard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('hifz_daily_pages')->default(3);   // pages a day at most in the daily review
        });

        Schema::create('hifz_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->unsignedSmallInteger('page');
            $table->string('state', 10);                            // learning | memorized
            $table->float('stability');                             // days until it would be 90% likely remembered
            $table->float('difficulty');                            // 1 (easy) to 10
            $table->timestampTz('due_at');
            $table->timestampTz('last_reviewed_at');
            $table->unsignedInteger('reviews')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->timestampsTz();
            $table->tenantKey();
            $table->unique(['academy_id', 'student_id', 'page']);
            $table->index(['academy_id', 'student_id', 'due_at']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
        });
        DB::statement("alter table hifz_pages add constraint hifz_pages_state check (state in ('learning', 'memorized'))");
        DB::statement('alter table hifz_pages add constraint hifz_pages_page check (page between 1 and 604)');
        TenantTable::enableRls('hifz_pages');

        Schema::create('hifz_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->unsignedSmallInteger('page');
            $table->unsignedSmallInteger('grade');                  // 1 again, 2 hard, 3 good, 4 easy
            $table->string('source', 10);                           // teacher | report | check | wird | self
            $table->uuid('source_id');                              // the report, check, recitation… it came from
            $table->timestampTz('reviewed_at');
            $table->timestampTz('created_at');
            $table->tenantKey();
            $table->unique(['academy_id', 'source', 'source_id', 'page']);
            $table->index(['academy_id', 'student_id', 'reviewed_at']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
        });
        DB::statement('alter table hifz_reviews add constraint hifz_reviews_grade check (grade between 1 and 4)');
        TenantTable::enableRls('hifz_reviews');

        Schema::create('hifz_recitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->unsignedSmallInteger('page');
            $table->string('status', 10);                           // pending | ready | failed
            $table->string('reason', 20)->nullable();               // why it failed (AiFailed's reason)
            $table->string('file_path')->nullable();                // the recording, until it is heard
            $table->string('mime', 100)->nullable();
            $table->jsonb('result')->nullable();                    // {verses, extra, summary}
            $table->unsignedSmallInteger('grade')->nullable();
            $table->uuid('recited_by');                             // the student, or a guardian on their screen
            $table->timestampTz('created_at');
            $table->timestampTz('checked_at')->nullable();
            $table->tenantKey();
            $table->index(['academy_id', 'student_id', 'created_at']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
        });
        DB::statement("alter table hifz_recitations add constraint hifz_recitations_status check (status in ('pending', 'ready', 'failed'))");
        TenantTable::enableRls('hifz_recitations');

        // A page reviewed, marked or recited refreshes the learner's family screens.
        DB::statement(<<<'SQL'
            create or replace function lms_hifz_live() returns trigger language plpgsql as $$
            begin
                if tg_op = 'DELETE' then
                    perform lms_notify_family('hifz', old.academy_id, old.student_id);
                else
                    perform lms_notify_family('hifz', new.academy_id, new.student_id);
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger hifz_pages_live after insert or update or delete on hifz_pages for each row execute function lms_hifz_live()');
        DB::statement('create trigger hifz_recitations_live after insert or update on hifz_recitations for each row execute function lms_hifz_live()');
    }

    public function down(): void
    {
        Schema::dropIfExists('hifz_recitations');
        Schema::dropIfExists('hifz_reviews');
        Schema::dropIfExists('hifz_pages');
        DB::statement('drop function if exists lms_hifz_live()');
        Schema::table('learning_settings', fn (Blueprint $table) => $table->dropColumn('hifz_daily_pages'));
    }
};
