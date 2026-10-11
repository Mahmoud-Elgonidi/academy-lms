<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * An academy's side of the games (docs/architecture/02, section 18): each
 * round a learner plays (which game and version, which of their own practice
 * items it was handed, how it went), the creatures they hatched on the
 * islands, the academy's choice (games on or off, minutes a day) and a
 * guardian's for their child. The words of the items stay in the
 * curriculum; a round keeps only their ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_plays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->uuid('played_by');                          // the student, or a guardian on their screen
            $table->uuid('game_id');                            // the platform's catalog (no key across)
            $table->string('game_key', 41);
            $table->uuid('version_id');
            $table->string('focus', 12);                        // homework | last_lesson | review | current | free
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('homework_id')->nullable();
            $table->jsonb('items');                             // [{id, node, kind}] as handed to the game
            $table->jsonb('answers')->default('[]');            // [{item, correct}] in the order they came
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->integer('seconds')->nullable();
            $table->smallInteger('correct')->nullable();
            $table->smallInteger('total');
            $table->smallInteger('stars')->nullable();
            $table->tenantKey();
            $table->index(['academy_id', 'student_id', 'started_at']);
        });
        DB::statement("alter table game_plays add constraint game_plays_shape check (jsonb_typeof(items) = 'array' and jsonb_typeof(answers) = 'array' and total > 0)");
        DB::statement('alter table game_plays add constraint game_plays_done check ((finished_at is null) = (stars is null) and coalesce(stars, 0) between 0 and 3)');

        Schema::create('creature_finds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('student_id');
            $table->string('creature_key', 60);                 // the platform's catalog
            $table->string('source', 12);                       // stars | mastery
            $table->uuid('node_id')->nullable();                // the lesson mastered
            $table->uuid('play_id')->nullable();                // the round whose stars hatched it
            $table->string('nickname', 24)->nullable();         // the name the learner gave it
            $table->boolean('buddy')->default(false);           // the one that keeps them company (one at most)
            $table->timestampTz('found_at');
            $table->tenantKey();
            $table->unique(['academy_id', 'student_id', 'creature_key']);
        });
        DB::statement("alter table creature_finds add constraint creature_finds_source check (source in ('stars', 'mastery'))");
        DB::statement('create unique index creature_finds_one_buddy on creature_finds (academy_id, student_id) where buddy');

        Schema::create('game_settings', function (Blueprint $table) {
            $table->tenant();
            $table->boolean('enabled')->default(true);
            $table->smallInteger('daily_minutes')->nullable()->default(30);   // null: no limit
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->primary('academy_id');
        });

        Schema::create('game_limits', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('student_id');
            $table->boolean('enabled')->default(true);
            $table->smallInteger('daily_minutes')->nullable();                // null: the academy's
            $table->boolean('night_lock')->default(false);                    // no play from 21:00 to 07:00 on the learner's clock
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->primary(['academy_id', 'student_id']);
        });
        DB::statement('alter table game_settings add constraint game_settings_minutes check (coalesce(daily_minutes, 1) between 1 and 600)');
        DB::statement('alter table game_limits add constraint game_limits_minutes check (coalesce(daily_minutes, 1) between 1 and 600)');

        foreach (['game_plays', 'creature_finds', 'game_settings', 'game_limits'] as $table) {
            TenantTable::enableRls($table);
        }

        // A hatched creature or a changed limit refreshes the learner's family screens (a round finished already does, through practice).
        DB::statement(<<<'SQL'
            create or replace function lms_games_family_live() returns trigger language plpgsql as $$
            begin
                perform lms_notify_family('practice', new.academy_id, new.student_id);
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger creature_finds_live after insert on creature_finds for each row execute function lms_games_family_live()');
        DB::statement('create trigger game_limits_live after insert or update on game_limits for each row execute function lms_games_family_live()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_limits');
        Schema::dropIfExists('game_settings');
        Schema::dropIfExists('creature_finds');
        Schema::dropIfExists('game_plays');
        DB::statement('drop function if exists lms_games_family_live()');
    }
};
