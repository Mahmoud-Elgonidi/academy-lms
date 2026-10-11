<?php

namespace Modules\Games\Filament\Family;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\HatchCreature;
use Modules\Games\Actions\NameCreature;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Games\Support\HatchNames;
use Modules\Learning\Contracts\LearningApi;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Http\ProblemException;

/**
 * The games on the learner's journey in the family area (docs/architecture/02,
 * section 18), in the journey's play hook: the journey asks for a round on a
 * station or for a homework ("journey-play") and for the egg its stars filled
 * ("journey-hatch"); the round plays in a sandboxed frame (shared::games.player),
 * its result comes back here (stars, the race, a creature hatched, homework
 * handed in), and the journey hears "journey-changed". A creature hatched comes
 * out of its egg and the child names it. A guardian may play together with the
 * child on their screen, never hatch or name.
 */
final class JourneyPlay extends Component
{
    /** The round being played: its id, page, items and look (what the player hands the game). */
    public ?array $round = null;

    /** The last round's result, for the overlay. */
    public ?array $result = null;

    /** A creature that just hatched from the egg on the map, to name. */
    public ?array $hatched = null;

    /** @param  ?array{game?: string, station?: string, homework?: string}  $start  a round to start on arrival (a homework link) */
    public function mount(?array $start = null): void
    {
        if (is_string($start['game'] ?? null)) {
            $this->play($start['game'], $start['station'] ?? null, $start['homework'] ?? null);
        }
    }

    public function learnerId(): ?string
    {
        return app(FamilyView::class)->current((string) auth()->id())?->id;
    }

    public function isGuardian(): bool
    {
        return $this->learnerId() !== null && $this->learnerId() !== (string) auth()->id();
    }

    /** The look of the viewer's screen (a guardian's is classic). */
    public function look(): string
    {
        return app(FamilyView::class)->stage((string) auth()->id())->value;
    }

    #[On('journey-play')]
    public function play(string $game, ?string $station = null, ?string $homework = null): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null, 404);
        try {
            $r = app(StartPlay::class)->handle($learner, (string) auth()->id(), $game, null, $homework, $homework === null ? $station : null);
        } catch (ProblemException $e) {
            Notification::make()->warning()->title(__('shared::problems.'.$e->type))->body($e->type === 'games-not-enough-items' ? __('games::games.not_enough') : null)->send();

            return;
        }
        $ar = app()->getLocale() === 'ar';
        $island = $r['play']->island_key ?? $r['game']->islandKey;
        $this->result = null;
        $this->hatched = null;
        $this->round = [
            'play' => $r['play']->id,
            'game' => $r['game']->key,
            'station' => $homework === null ? $station : null,
            'homework' => $homework,
            'title' => $r['game']->title(),
            'how' => $r['game']->howToPlay(),
            'island' => $island,
            'sky' => collect(app(GameCatalog::class)->islands())->firstWhere('key', $island)?->colors['sky'] ?? null,
            'src' => route('games.play', $r['game']->versionId),
            'items' => array_values($r['round']),
            'stage' => $r['stage'],
            'locale' => $ar ? 'ar' : 'en',
            'dir' => $ar ? 'rtl' : 'ltr',
            'focus' => $r['focus']?->title,
            'best_before' => $homework === null && $station !== null ? $this->bestOn($learner, $station) : null,
        ];
    }

    /** @param  list<array{item: string, correct: bool}>  $answers  what the game reported, from the player */
    public function finish(array $answers): void
    {
        $learner = $this->learnerId();
        $play = $this->round === null ? null : GamePlay::query()->where('student_id', $learner)->find($this->round['play']);
        abort_if($play === null, 404);
        try {
            $r = app(FinishPlay::class)->handle($play, (string) auth()->id(), $answers);
        } catch (ValidationException) {
            $r = app(FinishPlay::class)->handle($play, (string) auth()->id(), []);
        }
        $p = $r['play'];
        $stars = app(LearningApi::class)->stars((string) $learner);
        $this->result = [
            'play' => $p->id,
            'stars' => (int) $p->stars,
            'correct' => (int) $p->correct,
            'total' => $p->total,
            'total_stars' => $stars,
            'seconds' => $p->seconds,
            'best_before' => $this->round['best_before'],
            /** A round on a station races the best time on it. */
            'raced' => $this->round['station'] !== null,
            'creature' => $r['creature'] === null ? null : $this->hatchedView($learner, $r['creature']),
            'egg' => ['have' => $stars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG],
            'homework' => $r['handed_in'] ? $r['homework'] : null,
            'game' => $this->round['game'],
            // A homework's round was on one lesson: playing again goes on that lesson once the homework is handed in.
            'station' => $this->round['station'] ?? (count($nodes = array_unique(array_column((array) $p->items, 'node'))) === 1 ? reset($nodes) : null),
            'homework_id' => $r['handed_in'] ? null : $this->round['homework'],
            'title' => $this->round['title'],
        ];
        $this->round = null;
        $this->dispatch('journey-changed');
    }

    /** The egg the journey's stars filled, tapped on the map: the next creature hatches on the island they are on. */
    #[On('journey-hatch')]
    public function hatch(?string $island = null): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null || $this->isGuardian(), 403);
        $creature = app(HatchCreature::class)->byStars($learner, $island);
        $this->dispatch('journey-changed');
        if ($creature === null) {
            Notification::make()->info()->title(__('games::games.hatch.not_ready'))->send();

            return;
        }
        $this->round = null;
        $this->result = null;
        $this->hatched = $this->hatchedView($learner, $creature);
    }

    /**
     * A creature just hatched, for its egg: how it looks, where it lives, how many the learner has
     * now, and three names to choose from (its own and two more).
     *
     * @return array<string, mixed>
     */
    private function hatchedView(string $learner, CatalogCreature $c): array
    {
        $catalog = app(GameCatalog::class);
        $island = collect($catalog->islands())->firstWhere('key', $c->islandKey);

        return [
            'key' => $c->key, 'name' => $c->name(), 'emoji' => $c->emoji, 'rarity' => $c->rarity, 'look' => $c->look,
            'island' => $island?->name() ?? '',
            'found' => CreatureFind::query()->where('student_id', $learner)->count(),
            'total' => array_sum(array_map(fn ($i) => count($i->creatures), $catalog->islands())),
            'names' => HatchNames::for($c),
        ];
    }

    /** The best time on a station before this round (the race against yourself). */
    private function bestOn(string $learner, string $station): ?int
    {
        $best = GamePlay::query()->where('student_id', $learner)->whereNotNull('finished_at')->whereNotNull('seconds')
            ->latest('finished_at')->limit(300)->get(['items', 'seconds'])
            ->filter(fn (GamePlay $p) => array_values(array_unique(array_column((array) $p->items, 'node'))) === [$station])
            ->min('seconds');

        return $best === null ? null : (int) $best;
    }

    /** The child names the creature that just hatched, and goes to see it in the album. */
    public function nameCreature(string $key, string $name): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null || $this->isGuardian(), 403);
        app(NameCreature::class)->handle($learner, $key, $name);
        $this->result = null;
        $this->hatched = null;
        $this->redirect(FamilyCreatures::getUrl(['new' => $key]), navigate: true);
    }

    public function again(): void
    {
        $last = $this->result;
        $this->result = null;
        if ($last !== null) {
            $this->play($last['game'], $last['station'], $last['homework_id']);
        }
    }

    public function leave(): void
    {
        $this->round = null;
        $this->result = null;
        $this->hatched = null;
    }

    public function render(): View
    {
        return view('games::filament.family.journey-play');
    }
}
