<?php

namespace Modules\Games\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Learning\Contracts\LearningApi;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * A learner hatches the next creature (docs/architecture/02, section 18):
 * every Round::STARS_PER_EGG stars of their journey (lessons, rounds,
 * homework, mastered lessons, chests: LearningApi::stars) hatch one on the
 * island they are on (then the next island with one left), and every lesson
 * the teacher marks mastered hatches the next in the world's order. Each
 * creature once; a learner who has them all hatches nothing more.
 */
final class HatchCreature
{
    public function __construct(
        private readonly GameCatalog $catalog,
        private readonly LearningApi $learning,
    ) {}

    /** An egg for each STARS_PER_EGG stars of the journey not hatched yet (after a round, or when the learner taps a full egg). */
    public function byStars(string $studentId, ?string $islandKey, ?string $playId = null): ?CatalogCreature
    {
        $stars = $this->learning->stars($studentId);
        $hatched = CreatureFind::query()->where('student_id', $studentId)->where('source', 'stars')->count();
        if (intdiv($stars, Round::STARS_PER_EGG) <= $hatched) {
            return null;
        }

        return $this->hatch($studentId, $islandKey, ['source' => 'stars', 'play_id' => $playId]);
    }

    /** A lesson mastered: once per lesson. */
    public function byMastery(string $studentId, string $nodeId): ?CatalogCreature
    {
        if (CreatureFind::query()->where('student_id', $studentId)->where('node_id', $nodeId)->exists()) {
            return null;
        }

        return $this->hatch($studentId, null, ['source' => 'mastery', 'node_id' => $nodeId]);
    }

    /** The next creature not found yet: on this island first, else in the world's order. */
    public function next(string $studentId, ?string $islandKey): ?CatalogCreature
    {
        $found = CreatureFind::query()->where('student_id', $studentId)->pluck('creature_key')->all();
        $islands = $this->catalog->islands();
        usort($islands, fn ($a, $b) => [$a->key !== $islandKey, $a->sort] <=> [$b->key !== $islandKey, $b->sort]);
        foreach ($islands as $island) {
            foreach ($island->creatures as $creature) {
                if (! in_array($creature->key, $found, true)) {
                    return $creature;
                }
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $why */
    private function hatch(string $studentId, ?string $islandKey, array $why): ?CatalogCreature
    {
        $creature = $this->next($studentId, $islandKey);
        if ($creature === null) {
            return null;
        }
        try {
            DB::transaction(fn () => CreatureFind::query()->create(['student_id' => $studentId, 'creature_key' => $creature->key, 'found_at' => now()] + $why));
        } catch (UniqueConstraintViolationException) {
            return null;   // hatched at the same moment by another round
        }

        return $creature;
    }
}
