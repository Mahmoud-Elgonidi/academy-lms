<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A lesson's text is words the academy typed, nothing else (docs/architecture
 * 02, section 6): any other kind of text a lesson held was taken over by the
 * module it belongs to before this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table curriculum_nodes no force row level security');
        DB::statement("update curriculum_nodes set text_source = null where text_source is not null and text_source->>'kind' <> 'text'");
        DB::statement('alter table curriculum_nodes force row level security');
        DB::statement('alter table curriculum_nodes drop constraint curriculum_nodes_text_source');
        DB::statement("alter table curriculum_nodes add constraint curriculum_nodes_text_source check (
            text_source is null
            or (kind = 'lesson' and text_source->>'kind' = 'text' and jsonb_typeof(text_source->'text') = 'string' and length(text_source->>'text') <= 5000))");
    }

    public function down(): void {}
};
