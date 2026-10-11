<?php

namespace Modules\Games\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * A learner hatches the next creature (docs/architecture/02, section 18):
 * every Round::STARS_PER_EGG stars won in the games hatch one on the island
 * they were won on (then the next island with one left), and every lesson
 * the teacher marks mastered hatches the next in the world's order. Each
 * creature once; a learner who has them all hatches nothing more.
 */
final class HatchCreature
{
    public function __construct(private readonly GameCatalog $catalog) {}

    /** After a round: an egg for each STARS_PER_EGG stars not hatched yet. */
    public function byStars(GamePlay $play, string $islandKey): ?CatalogCreature
    {
        $stars = (int) GamePlay::query()->where('student_id', $play->student_id)->whereNotNull('finished_at')->sum('stars');
        $hatched = CreatureFind::query()->where('student_id', $play->student_id)->where('source', 'stars')->count();
        if (intdiv($stars, Round::STARS_PER_EGG) <= $hatched) {
            return null;
        }

        return $this->hatch($play->student_id, $islandKey, ['source' => 'stars', 'play_id' => $play->id]);
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
