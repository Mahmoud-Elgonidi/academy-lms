<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The Quran module takes what was the generic modules' (docs/architecture/02,
 * section 26), without moving a row of its own tables:
 *
 * - quran_lesson_passages: a lesson's passage of the Quran (surah, verses),
 *   which curriculum_nodes.text_source used to hold as {kind: quran}. Those
 *   passages are copied here, by lesson, and taken off the curriculum (whose
 *   text is typed words only from now on); the lesson keeps its id, so its
 *   reports, homework and mushaf find the same passage.
 * - subjects whose curricula read the Quran become Quran subjects (track
 *   hifz), so their courses keep their hifz map (HifzMap::follows).
 * - quran_settings: the academy's Quran settings, starting with the daily
 *   review's pages (learning_settings.hifz_daily_pages until now).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_lesson_passages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('node_id');                                // the curriculum's lesson
            $table->unsignedSmallInteger('surah');
            $table->unsignedSmallInteger('from_verse');
            $table->unsignedSmallInteger('to_verse');
            $table->timestampsTz();
            $table->tenantKey();
            $table->unique(['academy_id', 'node_id']);
            $table->tenantForeign('node_id', 'curriculum_nodes')->cascadeOnDelete();
        });
        DB::statement('alter table quran_lesson_passages add constraint quran_lesson_passages_verses check (surah between 1 and 114 and from_verse >= 1 and to_verse >= from_verse)');
        TenantTable::enableRls('quran_lesson_passages');

        Schema::create('quran_settings', function (Blueprint $table) {
            $table->tenant();
            $table->unsignedSmallInteger('hifz_daily_pages')->default(3);   // pages a day at most in the daily review
            $table->timestampsTz();
            $table->primary('academy_id');
        });
        DB::statement('alter table quran_settings add constraint quran_settings_daily check (hifz_daily_pages between 1 and 20)');
        TenantTable::enableRls('quran_settings');

        // The owner reads and writes every academy's rows only with row-level security set aside for a moment.
        $tables = ['curriculum_nodes', 'curriculum_versions', 'programs', 'subjects', 'quran_lesson_passages', 'quran_settings', 'learning_settings'];
        foreach ($tables as $table) {
            DB::statement("alter table {$table} no force row level security");
        }
        DB::statement(<<<'SQL'
            insert into quran_lesson_passages (id, academy_id, node_id, surah, from_verse, to_verse, created_at, updated_at)
            select gen_random_uuid(), academy_id, id, (text_source->>'surah')::int, (text_source->>'from')::int, (text_source->>'to')::int, now(), now()
            from curriculum_nodes
            where text_source->>'kind' = 'quran'
              and (text_source->>'surah')::int between 1 and 114 and (text_source->>'from')::int >= 1
              and (text_source->>'to')::int >= (text_source->>'from')::int
        SQL);
        DB::statement(<<<'SQL'
            update subjects s set kind = 'quran', quran_track = 'hifz'
            where s.kind = 'general' and exists (
                select 1 from quran_lesson_passages q
                join curriculum_nodes n on n.academy_id = q.academy_id and n.id = q.node_id
                join curriculum_versions v on v.academy_id = n.academy_id and v.id = n.version_id
                join programs p on p.academy_id = v.academy_id and p.id = v.program_id
                where p.academy_id = s.academy_id and p.subject_id = s.id)
        SQL);
        DB::statement("update curriculum_nodes set text_source = null where text_source->>'kind' = 'quran'");
        DB::statement(<<<'SQL'
            insert into quran_settings (academy_id, hifz_daily_pages, created_at, updated_at)
            select academy_id, least(greatest(hifz_daily_pages, 1), 20), now(), now() from learning_settings where hifz_daily_pages <> 3
        SQL);
        foreach ($tables as $table) {
            DB::statement("alter table {$table} force row level security");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_settings');
        Schema::dropIfExists('quran_lesson_passages');
    }
};
