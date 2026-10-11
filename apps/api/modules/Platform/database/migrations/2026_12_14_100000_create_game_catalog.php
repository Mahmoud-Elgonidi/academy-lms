<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Platform\Games\StarterCatalog;

/*
 * The platform's games (docs/architecture/02, section 18): a world of
 * islands, each with the creatures a learner hatches there, and the games
 * that live on them. A game is published as versions that never change (the
 * page and what it says about itself); one is live. The platform team makes
 * a new game, or a new version of one, in the studio: a draft holds the
 * story and what the designer (Claude) made of it, and every word the team
 * and the designer exchanged on it stays as it was written.
 *
 * Only the platform reads and writes these (pgsql_platform); the academies
 * see the live catalog through SharedKernel's GameCatalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_islands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->citext('key')->unique();
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->string('story_ar', 400)->default('');
            $table->string('story_en', 400)->default('');
            $table->string('emoji', 16);
            $table->jsonb('colors');                               // {sky, sea, land, accent}
            $table->string('music', 20);                           // the island's scale, played by the screens
            $table->smallInteger('sort')->default(0);
            $table->timestampsTz();
        });
        DB::statement("alter table game_islands add constraint game_islands_key check (key ~ '^[a-z][a-z0-9-]{1,30}$')");

        Schema::create('game_creatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('island_id')->constrained('game_islands')->restrictOnDelete();
            $table->citext('key')->unique();
            $table->string('name_ar', 60);
            $table->string('name_en', 60);
            $table->string('emoji', 16);
            $table->string('rarity', 12);
            $table->jsonb('look')->default('{}');               // how it is drawn: {kind, body, wing, belly, tuft, beak, crest, pitch}
            $table->string('story_ar', 200)->default('');
            $table->string('story_en', 200)->default('');
            $table->smallInteger('sort')->default(0);
            $table->timestampsTz();
        });
        DB::statement("alter table game_creatures add constraint game_creatures_rarity check (rarity in ('common', 'rare', 'legendary'))");

        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->citext('key')->unique();
            $table->foreignUuid('island_id')->constrained('game_islands')->restrictOnDelete();
            $table->string('title_ar', 80);
            $table->string('title_en', 80);
            $table->string('summary_ar', 300)->default('');
            $table->string('summary_en', 300)->default('');
            $table->jsonb('stages');                               // kids, middle, teen, classic
            $table->jsonb('kinds');                                // SharedKernel\Games\ItemKind values
            $table->string('status', 10)->default('draft');        // draft (never published), live, retired
            $table->uuid('current_version_id')->nullable();
            $table->smallInteger('sort')->default(0);
            $table->uuid('created_by')->nullable();                // platform_admins.id (null: shipped with the platform)
            $table->timestampsTz();
        });
        DB::statement("alter table games add constraint games_key check (key ~ '^[a-z][a-z0-9-]{1,40}$')");
        DB::statement("alter table games add constraint games_status check (status in ('draft', 'live', 'retired'))");
        DB::statement("alter table games add constraint games_lists check (jsonb_typeof(stages) = 'array' and jsonb_typeof(kinds) = 'array')");
        DB::statement("alter table games add constraint games_live check (status <> 'live' or current_version_id is not null)");

        Schema::create('game_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->integer('version');
            $table->text('html');                                  // the page, without the platform's bridge (GamePage adds it)
            $table->char('sha256', 64);
            $table->jsonb('manifest');                             // kinds, stages, min/max items, how to play
            $table->uuid('draft_id')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at');
            $table->unique(['game_id', 'version']);
        });
        DB::statement('alter table games add constraint games_current_version foreign key (current_version_id) references game_versions (id) on delete restrict deferrable initially deferred');

        Schema::create('game_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->nullable()->constrained('games')->restrictOnDelete();   // a new version of a game, else a new game
            $table->foreignUuid('island_id')->constrained('game_islands')->restrictOnDelete();
            $table->jsonb('stages');
            $table->jsonb('kinds');
            $table->string('topic', 160)->default('');             // what the academies teach with it (Quran memorisation, Arabic letters, ...)
            $table->text('story');
            $table->string('status', 12)->default('designing');    // designing, ready, failed, published, discarded
            $table->string('title_ar', 80)->nullable();
            $table->string('title_en', 80)->nullable();
            $table->string('summary_ar', 300)->nullable();
            $table->string('summary_en', 300)->nullable();
            $table->jsonb('manifest')->nullable();
            $table->text('html')->nullable();
            $table->jsonb('issues')->default('[]');                // what the package check found
            $table->text('error')->nullable();                     // why the last design failed
            $table->string('served_model', 80)->nullable();
            $table->jsonb('usage')->nullable();                    // the designer's tokens, for the console
            $table->integer('rounds')->default(0);                 // how many times the designer worked on it
            $table->uuid('created_by');
            $table->timestampTz('designed_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->uuid('published_version_id')->nullable();
            $table->timestampsTz();
        });
        DB::statement("alter table game_drafts add constraint game_drafts_status check (status in ('designing', 'ready', 'failed', 'published', 'discarded'))");
        DB::statement("alter table game_drafts add constraint game_drafts_lists check (jsonb_typeof(stages) = 'array' and jsonb_typeof(kinds) = 'array' and jsonb_typeof(issues) = 'array')");
        DB::statement("create index game_drafts_open on game_drafts (updated_at desc) where status in ('designing', 'ready', 'failed')");

        Schema::create('game_draft_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('draft_id')->constrained('game_drafts')->restrictOnDelete();
            $table->string('role', 10);                            // admin (the story, then each change asked), designer (what it made and why)
            $table->text('body');
            $table->uuid('admin_id')->nullable();
            $table->timestampTz('created_at');
            $table->index(['draft_id', 'created_at']);
        });
        DB::statement("alter table game_draft_turns add constraint game_draft_turns_role check (role in ('admin', 'designer'))");

        // A published version and the conversation on a draft stay as they were written.
        DB::unprepared(<<<'SQL'
            create or replace function lms_games_keep() returns trigger language plpgsql as $$
            begin
                raise exception '% rows are kept as they were written', tg_table_name;
            end $$;
            create trigger lms_game_versions_keep before update or delete on game_versions
                for each row execute function lms_games_keep();
            create trigger lms_game_draft_turns_keep before update or delete on game_draft_turns
                for each row execute function lms_games_keep();
            SQL);

        // The studio's open screens follow their drafts (Platform\Live\GameStudioChanged).
        DB::unprepared(<<<'SQL'
            create or replace function lms_game_drafts_live() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'game-studio:' || new.id);
                return null;
            end $$;
            create trigger lms_game_drafts_live after insert or update on game_drafts
                for each row execute function lms_game_drafts_live();
            SQL);

        // The world and the games the platform starts with.
        StarterCatalog::install(DB::connection());
        DB::statement('set constraints all immediate');   // check the deferred version keys now, before the tables are altered

        foreach (['game_islands', 'game_creatures', 'games', 'game_versions', 'game_drafts', 'game_draft_turns'] as $table) {
            DB::statement("alter table {$table} enable row level security");
            DB::statement("alter table {$table} force row level security");
            DB::statement("revoke insert, update, delete on {$table} from lms_app");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('game_draft_turns');
        Schema::dropIfExists('game_drafts');
        DB::statement('alter table if exists games drop constraint if exists games_current_version');
        Schema::dropIfExists('game_versions');
        Schema::dropIfExists('games');
        Schema::dropIfExists('game_creatures');
        Schema::dropIfExists('game_islands');
        DB::unprepared('drop function if exists lms_games_keep()');
        DB::unprepared('drop function if exists lms_game_drafts_live()');
    }
};
