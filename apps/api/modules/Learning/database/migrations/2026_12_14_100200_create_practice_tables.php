<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Practice (docs/architecture/02, section 18): what a learner practised in
 * the games is learning, so it is kept here. Each finished round is a row in
 * practice_rounds (never changed); practice_stats sums it up per course and
 * lesson for the teacher and the roadmap. Homework can come with practice:
 * the lessons of its report (practice_nodes), and the best round played for
 * it (practice_result), and the game the teacher chose for it
 * (practice_game, a catalog key; else the one that suits it best); homework
 * that is practice only ("accepts" practice) is handed in by playing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework', function (Blueprint $table) {
            $table->jsonb('practice_nodes')->nullable();            // the report's lessons whose practice the homework asks for
            $table->jsonb('practice_result')->nullable();           // {correct, total, stars, game, played_at}
            $table->string('practice_game', 41)->nullable();        // the catalog game the teacher chose to play it with
        });

        Schema::create('practice_rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('homework_id')->nullable();
            $table->string('game', 41);                             // the catalog game's key
            $table->string('focus', 12);                            // homework | last_lesson | review | current | free
            $table->smallInteger('correct');
            $table->smallInteger('total');
            $table->smallInteger('stars');
            $table->integer('seconds');
            $table->uuid('played_by');                              // the student, or a guardian on their screen
            $table->timestampTz('played_at');
            $table->tenantKey();
            $table->index(['academy_id', 'student_id', 'played_at']);
            $table->tenantForeign('homework_id', 'homework')->nullOnDelete();
        });
        DB::statement('alter table practice_rounds add constraint practice_rounds_counts check (total > 0 and correct between 0 and total and stars between 0 and 3 and seconds >= 0)');
        DB::statement("alter table practice_rounds add constraint practice_rounds_focus check (focus in ('homework', 'last_lesson', 'review', 'current', 'free'))");

        Schema::create('practice_stats', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('enrollment_id');
            $table->uuid('node_id');
            $table->uuid('student_id');
            $table->integer('rounds')->default(0);
            $table->integer('answered')->default(0);
            $table->integer('correct')->default(0);
            $table->timestampTz('last_at');
            $table->primary(['academy_id', 'enrollment_id', 'node_id']);
        });
        DB::statement('alter table practice_stats add constraint practice_stats_counts check (correct between 0 and answered and rounds >= 0)');

        TenantTable::enableRls('practice_rounds');
        TenantTable::enableRls('practice_stats');
        DB::statement('revoke update, delete on practice_rounds from lms_app');

        // A round played (or homework handed in by playing) refreshes the learner's family screens and the teacher's homework.
        DB::statement(<<<'SQL'
            create or replace function lms_practice_round_live() returns trigger language plpgsql as $$
            begin
                perform lms_notify_family('practice', new.academy_id, new.student_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger practice_rounds_live after insert on practice_rounds for each row execute function lms_practice_round_live()');
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_stats');
        Schema::dropIfExists('practice_rounds');
        DB::statement('drop function if exists lms_practice_round_live()');
        Schema::table('homework', function (Blueprint $table) {
            $table->dropColumn(['practice_nodes', 'practice_result', 'practice_game']);
        });
    }
};
