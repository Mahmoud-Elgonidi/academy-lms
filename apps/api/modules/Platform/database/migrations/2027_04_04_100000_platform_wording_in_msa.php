<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Arabic is Modern Standard Arabic now (docs/style/arabic.md). The
     * platform's page keeps what an owner saved, and the game world keeps what
     * the catalog installed: where either still holds an old default word for
     * word, it takes the new one. Anything someone wrote stays as it is.
     */
    public function up(): void
    {
        $pairs = require dirname(__DIR__).'/wording/msa-2027-04.php';
        $db = DB::connection('pgsql_platform');

        $site = $db->table('platform_site')->where('id', 1)->first();
        if ($site !== null) {
            $content = json_decode($site->content, true);
            $now = $this->swap($content, $pairs);
            if ($now !== $content) {
                $db->table('platform_site')->where('id', 1)->update(['content' => json_encode($now, JSON_UNESCAPED_UNICODE)]);
            }
        }

        foreach (['game_islands' => ['name_ar', 'story_ar'], 'game_creatures' => ['name_ar', 'story_ar'], 'games' => ['title_ar', 'summary_ar']] as $table => $columns) {
            foreach ($db->table($table)->select(['id', ...$columns])->get() as $row) {
                $now = [];
                foreach ($columns as $column) {
                    if (isset($pairs[$row->{$column}])) {
                        $now[$column] = $pairs[$row->{$column}];
                    }
                }
                if ($now !== []) {
                    $db->table($table)->where('id', $row->id)->update($now);
                }
            }
        }
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
