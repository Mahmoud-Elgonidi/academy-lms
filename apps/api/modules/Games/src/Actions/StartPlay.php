<?php

namespace Modules\Games\Actions;

use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GamePlay;
use Modules\Games\Queries\PlayAllowance;
use Modules\Games\Queries\PracticePool;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Http\ProblemException;

/**
 * A learner (or their guardian on their screen) starts a round
 * (docs/architecture/02, section 18): the game must be live and made for the
 * learner's look, the academy and the guardian must allow play and today's
 * minutes not be used up; the round is handed the learner's own practice
 * items (a quest's: homework, the last lesson, reviews; or all of them), of
 * the kinds the game plays, as many as it takes. The round keeps their ids;
 * the words go to the screen only.
 */
final class StartPlay
{
    public function __construct(
        private readonly PracticePool $pool,
        private readonly PlayAllowance $allowance,
        private readonly PeopleApi $people,
    ) {}

    /** @return array{play: GamePlay, game: CatalogGame, round: list<array<string, mixed>>, stage: string, focus: ?PracticeFocus} */
    public function handle(string $studentId, string $viewerId, string $gameIdOrKey, ?string $quest = null, ?string $homeworkId = null): array
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
}
