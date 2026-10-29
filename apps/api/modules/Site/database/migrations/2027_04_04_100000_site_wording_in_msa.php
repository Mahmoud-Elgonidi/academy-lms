<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Arabic is Modern Standard Arabic now (docs/style/arabic.md). An
     * academy's page keeps the words it saved, defaults included: where a
     * saved word is still an old default (or the demo's), word for word, it
     * takes the new one. Anything the academy wrote stays as it is.
     */
    public function up(): void
    {
        $pairs = require dirname(__DIR__).'/wording/msa-2027-04.php';

        // Every academy's row at once: its owner reads past row level security
        // only while it is not forced, inside this migration's transaction.
        DB::statement('alter table site_settings no force row level security');
        foreach (DB::table('site_settings')->select(['academy_id', 'content'])->get() as $row) {
            $content = json_decode($row->content, true);
            $now = $this->swap($content, $pairs);
            if ($now !== $content) {
                DB::table('site_settings')->where('academy_id', $row->academy_id)->update(['content' => json_encode($now, JSON_UNESCAPED_UNICODE)]);
            }
        }
        DB::statement('alter table site_settings force row level security');
    }

    public function down(): void
    {
        // The old words are not brought back.
    }

    /** @param  array<string, string>  $pairs */
    private function swap(mixed $value, array $pairs): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->swap($item, $pairs), $value);
        }

        return is_string($value) && isset($pairs[$value]) ? $pairs[$value] : $value;
    }
};
