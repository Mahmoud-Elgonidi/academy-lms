<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Site\Domain\Page\LegacyImport;
use Modules\Site\Domain\SiteContent;

/*
 * Every academy keeps the page it had (site-builder.md, 5.1): what it saved
 * in site_settings.content, over the defaults, becomes blocks that render
 * exactly as before (Domain\Page\LegacyImport), kept as its draft and its
 * published page. The history starts at its first real publish. The old
 * content stays where it was for one release (frozen: nothing writes it).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every academy's rows at once: the owner reads and writes past row level
        // security only while it is not forced, inside this migration's transaction.
        DB::statement('alter table site_settings no force row level security');
        DB::statement('alter table site_pages no force row level security');
        $now = now();
        foreach (DB::table('site_settings')->select(['academy_id', 'content'])->orderBy('academy_id')->get() as $row) {
            if (DB::table('site_pages')->where('academy_id', $row->academy_id)->exists()) {
                continue;
            }
            $content = json_decode((string) $row->content, true);
            $doc = json_encode(LegacyImport::fromContent((is_array($content) ? $content : []) + SiteContent::defaults()), JSON_UNESCAPED_UNICODE);
            DB::table('site_pages')->insert([
                'academy_id' => $row->academy_id, 'draft' => $doc, 'published' => $doc,
                'draft_revision' => 1, 'published_revision' => 1, 'template' => 'kids_families',
                'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::statement('alter table site_pages force row level security');
        DB::statement('alter table site_settings force row level security');
    }

    public function down(): void
    {
        DB::statement('alter table site_pages no force row level security');
        DB::table('site_pages')->where('template', 'kids_families')->where('draft_revision', 1)->where('published_revision', 1)->delete();
        DB::statement('alter table site_pages force row level security');
    }
};
