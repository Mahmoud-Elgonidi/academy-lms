<?php

namespace Modules\Games\Filament\Family;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Games\Actions\FinishPlay;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Models\GameLimit;
use Modules\Games\Models\GamePlay;
use Modules\Games\Models\GameSettings;
use Modules\Games\Queries\GamesWorld;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Http\ProblemException;

/**
 * The games in the family area (/my), in every look (docs/architecture/02,
 * section 18): the learner on screen's islands, creatures and quests from
 * their own practice; a round plays in a sandboxed frame (shared::games.player)
 * and its result (stars, a creature hatched, homework handed in) comes back
 * here. Opened with ?homework= it starts that homework's practice. A
 * guardian sets their child's play.
 */
final class FamilyGames extends Page
{
    protected string $view = 'games::filament.family.games';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'games';

    #[Url(as: 'homework')]
    public ?string $homework = null;

    /** The round being played: its id, page, items and look (what the player hands the game). */
    public ?array $round = null;

    /** The last round's result, for the overlay. */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return auth()->check() && app(FamilyView::class)->current((string) auth()->id()) !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('games::games.nav.'.app(FamilyView::class)->stage((string) auth()->id())->value);
    }

    public function getTitle(): string|Htmlable
    {
        return __('games::games.title.'.$this->look());
    }

    public function mount(): void
    {
        if ($this->homework !== null) {
            $homework = $this->homework;
            $this->homework = null;
            $quest = collect($this->world['quests'] ?? [])->firstWhere('homework_id', $homework);
            if ($quest !== null) {
                $this->play($quest['game']['id'], $quest['key']);
            }
        }
    }

    public function learnerId(): ?string
    {
        return app(FamilyView::class)->current((string) auth()->id())?->id;
    }

    /** The look of the learner's screen (a guardian's is classic). */
    public function look(): string
    {
        return app(FamilyView::class)->stage((string) auth()->id())->value;
    }

    /** The viewer's own channel: a round, a creature or a limit of the learner they follow raises "practice.changed" there. */
    public function channel(): string
    {
        return PersonalChannel::name((string) auth()->id());
    }

    public function isGuardian(): bool
    {
        return $this->learnerId() !== null && $this->learnerId() !== (string) auth()->id();
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function world(): ?array
    {
        $learner = $this->learnerId();

        return $learner === null ? null : app(GamesWorld::class)->for($learner);
    }

    /** @return array{enabled: bool, daily_minutes: ?int, academy: ?int, choices: list<int>}|null */
    #[Computed]
    public function limits(): ?array
    {
        if (! $this->isGuardian()) {
            return null;
        }
        $limit = GameLimit::query()->find($this->learnerId());

        return ['enabled' => $limit?->enabled ?? true, 'daily_minutes' => $limit?->daily_minutes, 'academy' => GameSettings::current()->daily_minutes, 'choices' => SaveGameLimit::MINUTES];
    }

    public function play(string $gameId, ?string $quest = null): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null, 404);
        try {
            $r = app(StartPlay::class)->handle($learner, (string) auth()->id(), $gameId, $quest);
        } catch (ProblemException $e) {
            Notification::make()->warning()->title(__('shared::problems.'.$e->type))->body($e->type === 'games-not-enough-items' ? __('games::games.not_enough') : null)->send();

            return;
        }
        $ar = app()->getLocale() === 'ar';
        $this->result = null;
        $this->round = [
            'play' => $r['play']->id,
            'game' => $r['game']->id,
            'quest' => $quest,
            'title' => $r['game']->title(),
            'how' => $r['game']->howToPlay(),
            'island' => $r['game']->islandKey,
            'src' => route('games.play', $r['game']->versionId),
            'items' => $r['round'],
            'stage' => $r['stage'],
            'locale' => $ar ? 'ar' : 'en',
            'dir' => $ar ? 'rtl' : 'ltr',
            'focus' => $r['focus']?->title,
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
        $stars = (int) GamePlay::query()->where('student_id', $learner)->whereNotNull('finished_at')->sum('stars');
        $this->result = [
            'stars' => (int) $p->stars,
            'correct' => (int) $p->correct,
            'total' => $p->total,
            'creature' => $r['creature'] === null ? null : ['name' => $r['creature']->name(), 'emoji' => $r['creature']->emoji, 'rarity' => $r['creature']->rarity],
            'egg' => ['have' => $stars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG],
            'homework' => $r['handed_in'] ? $r['homework'] : null,
            'game' => $this->round['game'],
            'quest' => $this->round['quest'],
            'title' => $this->round['title'],
        ];
        $this->round = null;
        unset($this->world);
    }

    public function again(): void
    {
        $last = $this->result;
        $this->result = null;
        if ($last !== null) {
            $this->play($last['game'], $last['quest']);
        }
    }

    public function leave(): void
    {
        $this->round = null;
        $this->result = null;
        unset($this->world);
    }

    public function saveLimits(bool $enabled, ?int $minutes): void
    {
        abort_unless($this->isGuardian(), 403);
        app(SaveGameLimit::class)->handle((string) $this->learnerId(), (string) auth()->id(), $enabled, $minutes ?: null);
        Notification::make()->success()->title(__('games::games.limits.saved'))->send();
        unset($this->limits, $this->world);
    }
}
