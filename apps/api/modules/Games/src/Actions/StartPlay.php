<?php

namespace Modules\Games\Actions;

use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GamePlay;
use Modules\Games\Queries\PlayAllowance;
use Modules\Games\Queries\PracticePool;
use Modules\Learning\Contracts\DTOs\JourneyStation;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Contracts\LearningApi;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Http\ProblemException;

/**
 * A learner (or their guardian on their screen) starts a round
 * (docs/architecture/02, section 18): the game must be live and made for the
 * learner's look, the academy and the guardian must allow play and today's
 * minutes not be used up; the round is handed the learner's own practice
 * items (a station's on their journey: one lesson they may practise now; a
 * quest's: homework, the last lesson, reviews; or all of them), of the kinds
 * the game plays, as many as it takes. The round keeps their ids; the words
 * go to the screen only.
 */
final class StartPlay
{
    public function __construct(
        private readonly PracticePool $pool,
        private readonly PlayAllowance $allowance,
        private readonly PeopleApi $people,
        private readonly LearningApi $learning,
    ) {}

    /** @return array{play: GamePlay, game: CatalogGame, round: list<array<string, mixed>>, stage: string, focus: ?PracticeFocus} */
    public function handle(string $studentId, string $viewerId, string $gameIdOrKey, ?string $quest = null, ?string $homeworkId = null, ?string $station = null): array
    {
        $allowance = $this->allowance->for($studentId);
        if (! $allowance['may']) {
            throw new ProblemException(403, 'games-'.$allowance['reason'], '', ['limit' => $allowance['limit'], 'used' => $allowance['used']]);
        }
        $stage = $this->people->studentProfile($studentId)->stage->value;
        $game = collect($this->pool->games($stage))->first(fn (CatalogGame $g) => $g->id === $gameIdOrKey || $g->key === strtolower($gameIdOrKey));
        if ($game === null) {
            throw new ProblemException(404, 'not-found');
        }

        if ($station !== null && $homeworkId === null && $quest === null) {
            return $this->onStation($studentId, $viewerId, $game, $stage, $station);
        }

        $focus = $this->pool->focus($studentId);
        $chosen = match (true) {
            $homeworkId !== null => collect($focus)->first(fn (PracticeFocus $f) => $f->homeworkId === $homeworkId),
            $quest !== null => collect($focus)->first(fn (PracticeFocus $f) => $f->key() === $quest),
            default => null,
        };
        if (($homeworkId !== null || $quest !== null) && $chosen === null) {
            throw new ProblemException(404, 'not-found');
        }
        $pool = $chosen !== null ? $this->pool->itemsOf($chosen->nodeIds) : $this->pool->all($focus);
        // A homework's items stay in its order; other rounds take a fresh mix each time.
        $items = Round::pick($game, $chosen?->reason === 'homework' ? $pool : collect($pool)->shuffle()->values()->all());
        if ($items === null) {
            throw new ProblemException(422, 'games-not-enough-items', '', ['needs' => $game->minItems]);
        }

        $play = GamePlay::query()->create([
            'student_id' => $studentId,
            'played_by' => $viewerId,
            'game_id' => $game->id,
            'game_key' => $game->key,
            'version_id' => $game->versionId,
            'focus' => $chosen?->reason ?? 'free',
            'enrollment_id' => $chosen?->enrollmentId,
            'homework_id' => $chosen?->homeworkId,
            'items' => array_map(fn (PracticeItem $i) => ['id' => $i->id, 'node' => $i->nodeId, 'kind' => $i->kind->value], $items),
            'started_at' => now(),
            'total' => count($items),
        ]);

        return ['play' => $play, 'game' => $game, 'round' => array_map(fn (PracticeItem $i) => $i->forRound(), $items), 'stage' => $stage, 'focus' => $chosen];
    }

    /**
     * A round on one lesson of the learner's journey (a station they may practise now): its own items, freshly mixed.
     *
     * @return array{play: GamePlay, game: CatalogGame, round: list<array<string, mixed>>, stage: string, focus: ?PracticeFocus}
     */
    private function onStation(string $studentId, string $viewerId, CatalogGame $game, string $stage, string $stationId): array
    {
        $station = collect($this->learning->journeyStations($studentId))->first(fn (JourneyStation $s) => $s->id === $stationId)
            ?? throw new ProblemException(404, 'not-found');
        $items = Round::pick($game, collect($this->pool->itemsOf([$station->id]))->shuffle()->values()->all());
        if ($items === null) {
            throw new ProblemException(422, 'games-not-enough-items', '', ['needs' => $game->minItems]);
        }

        $play = GamePlay::query()->create([
            'student_id' => $studentId,
            'played_by' => $viewerId,
            'game_id' => $game->id,
            'game_key' => $game->key,
            'version_id' => $game->versionId,
            'focus' => $station->state === 'here' ? 'current' : 'review',
            'enrollment_id' => $station->enrollmentId,
            'island_key' => $station->island,
            'items' => array_map(fn (PracticeItem $i) => ['id' => $i->id, 'node' => $i->nodeId, 'kind' => $i->kind->value], $items),
            'started_at' => now(),
            'total' => count($items),
        ]);

        return ['play' => $play, 'game' => $game, 'round' => array_map(fn (PracticeItem $i) => $i->forRound(), $items), 'stage' => $stage, 'focus' => null];
    }
}
