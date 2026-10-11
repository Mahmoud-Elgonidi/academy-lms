<?php

namespace Modules\Games\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GamePlay;
use Modules\Learning\Contracts\DTOs\PracticeRound;
use Modules\Learning\Contracts\LearningApi;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * A round ends (docs/architecture/02, section 18): what the game reported
 * (every try at an item) is scored (first try counts, stars from the share
 * right), the round is closed, Learning keeps it as practice (each lesson's
 * items, and the homework it was for, which a practice-only homework hands
 * in), and stars may hatch a creature. Finishing again answers the same.
 */
final class FinishPlay
{
    public function __construct(
        private readonly LearningApi $learning,
        private readonly HatchCreature $hatch,
        private readonly GameCatalog $catalog,
    ) {}

    /**
     * @param  list<array{item: string, correct: bool}>  $answers
     * @return array{play: GamePlay, creature: ?CatalogCreature, handed_in: bool, homework: ?string}
     */
    public function handle(GamePlay $play, string $viewerId, array $answers): array
    {
        if ($play->isFinished()) {
            return ['play' => $play, 'creature' => null, 'handed_in' => false, 'homework' => null];
        }
        $answers = Validator::make(['answers' => $answers], [
            'answers' => ['present', 'array', 'max:500'],
            'answers.*.item' => ['required', 'string', 'max:64'],
            'answers.*.correct' => ['required', 'boolean'],
        ])->validate()['answers'];
        $answers = array_values(array_map(fn (array $a) => ['item' => (string) $a['item'], 'correct' => filter_var($a['correct'], FILTER_VALIDATE_BOOLEAN)], $answers));

        return DB::transaction(function () use ($play, $viewerId, $answers) {
            $play = GamePlay::query()->lockForUpdate()->findOrFail($play->id);
            if ($play->isFinished()) {
                return ['play' => $play, 'creature' => null, 'handed_in' => false, 'homework' => null];
            }
            $now = CarbonImmutable::now();
            $score = Round::score($play->itemIds(), $answers);
            $play->forceFill([
                'answers' => $answers,
                'finished_at' => $now,
                'seconds' => min(Round::MAX_SECONDS, max(0, $now->getTimestamp() - $play->started_at->getTimestamp())),
                'correct' => $score['correct'],
                'stars' => $score['stars'],
            ])->save();

            $byNode = [];
            foreach ($play->items as $item) {
                $byNode[$item['node']] ??= ['answered' => 0, 'correct' => 0];
                if (array_key_exists($item['id'], $score['first'])) {
                    $byNode[$item['node']]['answered']++;
                    $byNode[$item['node']]['correct'] += $score['first'][$item['id']] ? 1 : 0;
                }
            }
            $game = $this->catalog->game($play->game_id);
            $recorded = $this->learning->recordPractice(new PracticeRound(
                studentId: $play->student_id,
                enrollmentId: $play->enrollment_id,
                homeworkId: $play->homework_id,
                game: $play->game_key,
                gameTitle: $game?->title() ?? $play->game_key,
                focus: $play->focus,
                correct: $score['correct'],
                total: $score['total'],
                stars: $score['stars'],
                seconds: (int) $play->seconds,
                playedBy: $viewerId,
                playedAt: $now,
                byNode: array_filter($byNode, fn (array $c) => $c['answered'] > 0),
            ));
            $creature = $this->hatch->byStars($play, $game?->islandKey ?? '');

            return ['play' => $play, 'creature' => $creature, 'handed_in' => $recorded->handedIn, 'homework' => $recorded->homeworkTitle];
        });
    }
}
