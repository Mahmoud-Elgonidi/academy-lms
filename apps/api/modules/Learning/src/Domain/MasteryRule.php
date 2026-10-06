<?php

namespace Modules\Learning\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * How one line of a report moves a lesson (docs/architecture/02, section 6):
 *
 * - mastered_once: the teacher marks it "mastered" once.
 * - score_twice: two reports scored 4 or more.
 *
 * After mastery the lesson comes back for review: in 7 days, then 14, 28 …
 * (up to 60) while reviews go well. A review marked "needs review" or scored
 * 2 or less brings it back at the next lesson and restarts the spacing. The
 * lesson still counts as mastered for the progress %.
 */
final class MasteryRule
{
    public const OUTCOMES = ['introduced', 'practiced', 'mastered', 'needs_review'];

    public const HIGH_SCORE = 4;

    public const FIRST_REVIEW_DAYS = 7;

    public const MAX_REVIEW_DAYS = 60;

    public static function apply(NodeProgress $p, string $outcome, ?int $score, string $rule, CarbonImmutable $at): NodeProgress
    {
        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw new InvalidArgumentException("Unknown outcome {$outcome}");
        }

        $seen = $p->timesSeen + 1;
        $high = $p->highScores + ($score !== null && $score >= self::HIGH_SCORE ? 1 : 0);
        $first = $p->firstSeenAt ?? $at;
        $weak = $outcome === 'needs_review' || ($score !== null && $score <= 2);

        if ($p->isMastered()) {
            $interval = $weak ? self::FIRST_REVIEW_DAYS : min(self::MAX_REVIEW_DAYS, 2 * ($p->reviewIntervalDays ?? self::FIRST_REVIEW_DAYS));

            return new NodeProgress($p->nodeId, $weak ? 'needs_review' : 'mastered', $seen, $high, $score, $outcome, $first, $p->masteredAt,
                $interval, $weak ? $at : $at->addDays($interval));
        }

        $mastered = match ($rule) {
            'score_twice' => $high >= 2,
            default => $outcome === 'mastered',
        };
        if ($mastered) {
            return new NodeProgress($p->nodeId, 'mastered', $seen, $high, $score, $outcome, $first, $at,
                self::FIRST_REVIEW_DAYS, $at->addDays(self::FIRST_REVIEW_DAYS));
        }

        return new NodeProgress($p->nodeId, $outcome === 'needs_review' ? 'needs_review' : 'in_progress', $seen, $high, $score, $outcome, $first,
            null, null, $outcome === 'needs_review' ? $at : null);
    }
}
