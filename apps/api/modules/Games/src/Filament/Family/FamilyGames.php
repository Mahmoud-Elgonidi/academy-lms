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
use Modules\Games\Actions\NameCreature;
use Modules\Games\Actions\SaveGameLimit;
use Modules\Games\Actions\StartPlay;
use Modules\Games\Domain\Round;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GameLimit;
use Modules\Games\Models\GamePlay;
use Modules\Games\Models\GameSettings;
use Modules\Games\Queries\GamesWorld;
use Modules\Games\Queries\GuardianGlance;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Learning\Contracts\LearningApi;
use Modules\People\Contracts\FamilyView;
use Modules\SharedKernel\Games\CatalogCreature;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Http\ProblemException;

/**
 * The games in the family area (/my), in every look (docs/architecture/02,
 * section 18): the learner on screen's islands, creatures and quests from
 * their own practice; a round plays in a sandboxed frame (shared::games.player)
 * and its result (stars, a creature hatched, homework handed in) comes back
 * here. Opened with ?homework= it starts that homework's practice, with
 * ?island= that island. A child's look is the islands world of the approved
 * design (games::filament.family.kids): a creature hatched comes out of its
 * egg there and the child names it. A guardian sets their child's play.
 */
final class FamilyGames extends Page
{
    protected string $view = 'games::filament.family.games';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'games';

    #[Url(as: 'homework')]
    public ?string $homework = null;

    /** An island to open on arrival (from the album). */
    #[Url(as: 'island')]
    public ?string $island = null;

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

    /** Every look has its own header in the screen itself (the map, the race, the review, a guardian's view). */
    public function getHeading(): string|Htmlable
    {
        return '';
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

    /** The learner's first name, for "Yousef's world". */
    public function learnerName(): string
    {
        $name = (string) app(FamilyView::class)->current((string) auth()->id())?->name;

        return explode(' ', trim($name))[0] ?? $name;
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

    /** @return array{enabled: bool, daily_minutes: ?int, night_lock: bool, academy: ?int, choices: list<int>}|null */
    #[Computed]
    public function limits(): ?array
    {
        if (! $this->isGuardian()) {
            return null;
        }
        $limit = GameLimit::query()->find($this->learnerId());

        return ['enabled' => $limit?->enabled ?? true, 'daily_minutes' => $limit?->daily_minutes, 'night_lock' => (bool) ($limit?->night_lock ?? false),
            'academy' => GameSettings::current()->daily_minutes, 'choices' => SaveGameLimit::MINUTES];
    }

    /** What a guardian sees of the child on screen: today's time, the week's practice, a creature hatched today. */
    #[Computed]
    public function glance(): ?array
    {
        return $this->isGuardian() ? app(GuardianGlance::class)->for((string) $this->learnerId()) : null;
    }

    /** @return list<array{id: string, name: string}> the children a guardian follows, for the tabs */
    public function children(): array
    {
        return array_map(fn ($l) => ['id' => $l->id, 'name' => explode(' ', trim($l->name))[0]],
            array_values(array_filter(app(FamilyView::class)->learners((string) auth()->id()), fn ($l) => ! $l->isSelf)));
    }

    public function chooseChild(string $learnerId): void
    {
        app(FamilyView::class)->choose((string) auth()->id(), $learnerId);
        $this->round = null;
        $this->result = null;
        unset($this->world, $this->limits, $this->glance);
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
            'sky' => collect($this->world['islands'] ?? [])->firstWhere('key', $r['game']->islandKey)['colors']['sky'] ?? null,
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
        // The quest's best time before this round, for the race (middle school).
        $before = collect($this->world['quests'] ?? [])->firstWhere('key', $this->round['quest'])['best_seconds'] ?? null;
        try {
            $r = app(FinishPlay::class)->handle($play, (string) auth()->id(), $answers);
        } catch (ValidationException) {
            $r = app(FinishPlay::class)->handle($play, (string) auth()->id(), []);
        }
        $p = $r['play'];
        $stars = (int) GamePlay::query()->where('student_id', $learner)->whereNotNull('finished_at')->sum('stars');
        $this->result = [
            'play' => $p->id,
            'stars' => (int) $p->stars,
            'correct' => (int) $p->correct,
            'total' => $p->total,
            'total_stars' => $stars,
            'seconds' => $p->seconds,
            'best_before' => $before,
            'creature' => $r['creature'] === null ? null : $this->hatched($learner, $r['creature']),
            'egg' => ['have' => $stars % Round::STARS_PER_EGG, 'need' => Round::STARS_PER_EGG],
            'homework' => $r['handed_in'] ? $r['homework'] : null,
            'game' => $this->round['game'],
            'quest' => $this->round['quest'],
            'title' => $this->round['title'],
        ];
        $this->round = null;
        unset($this->world, $this->glance);
    }

    /**
     * A creature just hatched, for its egg: how it looks, where it lives, how many the learner has
     * now, and three names to choose from (its own and two more).
     *
     * @return array<string, mixed>
     */
    private function hatched(string $learner, CatalogCreature $c): array
    {
        $catalog = app(GameCatalog::class);
        $island = collect($catalog->islands())->firstWhere('key', $c->islandKey);
        $names = (array) __('games::games.hatch.names');
        $at = crc32($c->key) % max(1, count($names));

        return [
            'key' => $c->key, 'name' => $c->name(), 'emoji' => $c->emoji, 'rarity' => $c->rarity, 'look' => $c->look,
            'island' => $island?->name() ?? '',
            'found' => CreatureFind::query()->where('student_id', $learner)->count(),
            'total' => array_sum(array_map(fn ($i) => count($i->creatures), $catalog->islands())),
            'names' => array_values(array_unique([$c->name(), $names[$at] ?? '', $names[($at + 5) % max(1, count($names))] ?? ''])),
        ];
    }

    /** The child names the creature that just hatched, and goes to see it in the album. */
    public function nameCreature(string $key, string $name): void
    {
        $learner = $this->learnerId();
        abort_if($learner === null || $this->isGuardian(), 403);
        app(NameCreature::class)->handle($learner, $key, $name);
        $this->result = null;
        $this->redirect(FamilyCreatures::getUrl(['new' => $key]), navigate: true);
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

    public function setMinutes(?int $minutes): void
    {
        $this->saveLimit(minutes: $minutes);
    }

    public function setNight(bool $on): void
    {
        $this->saveLimit(night: $on);
    }

    public function setEnabled(bool $on): void
    {
        $this->saveLimit(enabled: $on);
    }

    /** A guardian's choice for the child on screen; what is not given stays as it was. */
    private function saveLimit(?bool $enabled = null, int|false|null $minutes = false, ?bool $night = null): void
    {
        abort_unless($this->isGuardian(), 403);
        $now = $this->limits;
        app(SaveGameLimit::class)->handle((string) $this->learnerId(), (string) auth()->id(), $enabled ?? $now['enabled'],
            $minutes === false ? $now['daily_minutes'] : ($minutes ?: null), $night);
        Notification::make()->success()->title(__('games::games.guardian.saved'))->send();
        unset($this->limits, $this->world, $this->glance);
    }

    /** A guardian cheers the child for the creature they hatched (Learning's cheers: a few a day). */
    public function cheer(): void
    {
        abort_unless($this->isGuardian(), 403);
        try {
            app(LearningApi::class)->cheer((string) auth()->id(), (string) $this->learnerId(), 'heart');
            Notification::make()->success()->title(__('games::games.guardian.cheered'))->send();
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();
        }
    }
}
