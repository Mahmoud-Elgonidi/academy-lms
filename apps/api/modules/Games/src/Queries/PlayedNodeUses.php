<?php

namespace Modules\Games\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\NodeUses;

/** Rounds played on a lesson's items and creatures its mastery hatched: practice a published lesson keeps (tagged NodeUses::SOURCES). */
final class PlayedNodeUses implements NodeUses
{
    public function uses(array $nodeIds): array
    {
        $ids = '{'.implode(',', $nodeIds).'}';
        $played = DB::table('game_plays')
            ->whereRaw("exists (select 1 from jsonb_array_elements(items) as item where item->>'node' = any(?::text[]))", [$ids])->exists()
            || DB::table('creature_finds')->whereIn('node_id', $nodeIds)->exists();

        return $played ? ['practice'] : [];
    }
}
