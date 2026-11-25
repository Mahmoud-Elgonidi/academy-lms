<?php

namespace Modules\Learning\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\NodeUses;

/**
 * What Learning keeps about learners on curriculum nodes: lesson reports
 * (report_items), progress (enrollment_progress), homework that asks for a
 * lesson's practice, and practice stats. Tagged NodeUses::SOURCES, so a
 * published lesson with any of these is not deleted. A student's next lesson
 * (enrollment_positions) is no use: it is worked out again (LessonsRemoved).
 */
final class LearnerNodeUses implements NodeUses
{
    public function uses(array $nodeIds): array
    {
        $ids = '{'.implode(',', $nodeIds).'}';
        $found = [];
        if (DB::table('report_items')->whereIn('node_id', $nodeIds)->exists()) {
            $found[] = 'reports';
        }
        if (DB::table('enrollment_progress')->whereIn('node_id', $nodeIds)->exists()) {
            $found[] = 'progress';
        }
        if (DB::table('homework')->whereNotNull('practice_nodes')->whereRaw('jsonb_exists_any(practice_nodes, ?::text[])', [$ids])->exists()) {
            $found[] = 'homework';
        }
        if (DB::table('practice_stats')->whereIn('node_id', $nodeIds)->exists()) {
            $found[] = 'practice';
        }

        return $found;
    }
}
