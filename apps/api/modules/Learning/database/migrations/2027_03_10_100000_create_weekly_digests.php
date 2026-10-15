<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The weekly note to guardians (docs/architecture/02, section 22): when the
 * academy turns it on, each learner with a course running (or paused) gets
 * one note a week, on Friday evening on their own clock, to the guardians
 * who receive reports. One row per learner and week (the week's Saturday on
 * their clock) holds what went out: the week's facts and the words, with
 * the child as {child} (the name goes in only as it is sent), and the
 * recording of the week a guardian may hear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->boolean('weekly_digest')->default(false);
        });

        Schema::create('weekly_digests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->date('week_start');                             // the week's Saturday on the learner's clock
            $table->string('status', 10);                           // pending | sent | skipped
            $table->string('reason', 20)->nullable();               // why skipped: off, quiet, no_guardians
            $table->string('source', 6)->nullable();                // ai | facts: who wrote the words
            $table->text('text_ar')->nullable();                    // the words, the child as {child}
            $table->text('text_en')->nullable();
            $table->jsonb('facts')->nullable();                     // the week's facts it was written from
            $table->uuid('voice_submission_id')->nullable();        // the recording of the week, if any
            $table->unsignedSmallInteger('sent_to')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('sent_at')->nullable();
            $table->tenantKey();
            $table->unique(['academy_id', 'student_id', 'week_start']);
            $table->tenantForeign('student_id', 'users')->cascadeOnDelete();
        });
        // The recording leaving (erased, deleted) leaves the note without it; only its own column is cleared.
        DB::statement('alter table weekly_digests add constraint weekly_digests_voice_fk foreign key (academy_id, voice_submission_id) references homework_submissions (academy_id, id) on delete set null (voice_submission_id)');
        DB::statement("alter table weekly_digests add constraint weekly_digests_status check (status in ('pending', 'sent', 'skipped'))");
        TenantTable::enableRls('weekly_digests');
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_digests');
        Schema::table('learning_settings', fn (Blueprint $table) => $table->dropColumn('weekly_digest'));
    }
};
