<?php

namespace Modules\Learning\Domain;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\DTOs\CurriculumTree;

/**
 * What to teach in a lesson (docs/architecture/02, section 6, step 1): the
 * first lesson not yet mastered (and the one after it once the first has had
 * the sessions it usually takes), then up to two reviews that are due.
 */
final class TodaysPlan
{
    public const MAX_REVIEWS = 2;

    /**
     * @param  array<string, NodeProgress>  $progress  by lesson id
     * @return list<PlannedItem>
     */
    public static function for(CurriculumTree $tree, array $progress, CarbonImmutable $lessonStart): array
    {
        $plan = [];
        $next = array_values(array_filter($tree->lessons(), fn ($l) => ! (isset($progress[$l->id]) && $progress[$l->id]->isMastered())));
        if ($next !== []) {
            $first = $next[0];
            $seen = $progress[$first->id]->timesSeen ?? 0;
            $plan[] = new PlannedItem($first->id, 'next', $seen > 0 ? 'practiced' : 'introduced');
            if (isset($next[1]) && $seen >= $first->estSessions) {
                $plan[] = new PlannedItem($next[1]->id, 'next', ($progress[$next[1]->id]->timesSeen ?? 0) > 0 ? 'practiced' : 'introduced');
            }
        }

        $planned = array_map(fn (PlannedItem $i) => $i->nodeId, $plan);
        $due = array_filter($progress, fn (NodeProgress $p) => $tree->has($p->nodeId) && ! in_array($p->nodeId, $planned, true) && $p->isReviewDue($lessonStart));
        usort($due, fn (NodeProgress $a, NodeProgress $b) => $a->nextReviewAt <=> $b->nextReviewAt);
        foreach (array_slice($due, 0, self::MAX_REVIEWS) as $p) {
            $plan[] = new PlannedItem($p->nodeId, 'review', 'practiced');
        }

        return $plan;
    }
}
