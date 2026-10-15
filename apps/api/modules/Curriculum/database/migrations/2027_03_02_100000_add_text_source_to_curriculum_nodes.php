<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A lesson's text (docs/architecture/02, section 22): words the academy
 * typed, or a passage of the Quran by surah and verses (its words always
 * from the platform's copy). What the practice helper reads, and later the
 * passage a recitation is checked against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_nodes', function (Blueprint $table) {
            $table->jsonb('text_source')->nullable();      // {kind: text, text} | {kind: quran, surah, from, to}
        });
        DB::statement("alter table curriculum_nodes add constraint curriculum_nodes_text_source check (
            text_source is null
            or (kind = 'lesson' and text_source->>'kind' = 'text' and jsonb_typeof(text_source->'text') = 'string' and length(text_source->>'text') <= 5000)
            or (kind = 'lesson' and text_source->>'kind' = 'quran' and jsonb_typeof(text_source->'surah') = 'number'
                and jsonb_typeof(text_source->'from') = 'number' and jsonb_typeof(text_source->'to') = 'number'))");
    }

    public function down(): void
    {
        Schema::table('curriculum_nodes', function (Blueprint $table) {
            $table->dropColumn('text_source');
        });
    }
};
