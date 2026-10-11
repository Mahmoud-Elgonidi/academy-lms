<?php

namespace Modules\Learning\Filament\Teacher;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Learning\Actions\ChoosePracticeGame;
use Modules\Learning\Models\PracticeRoundEntry;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ReportFormQuery;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * A lesson's homework as a game (the approved design, Teacher; docs/
 * architecture/02, section 18): after the report, the teacher sees what was
 * taught (and what needs more work), picks a game that can play those
 * lessons' practice for this student's look, tries it, and sends it
 * (ChoosePracticeGame). The last round the student played shows below.
 */
final class HomeworkGame extends Page
{
    protected string $view = 'learning::filament.teacher.homework-game';

    protected static ?string $slug = 'reports/{report}/game';

    protected static bool $shouldRegisterNavigation = false;

    public SessionReport $sessionReport;

    public ?string $game = null;

    public bool $beforeNext = true;

    public bool $practiceOnly = true;

    /** The game being tried: its page and the items it is handed (nothing is recorded). */
    public ?array $trying = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::WriteReports->value);
    }

    public function mount(string $report): void
    {
        $this->sessionReport = SessionReport::query()->findOrFail($report);
        abort_unless($this->sessionReport->teacher_id === auth()->id(), 403);
        $homework = $this->sessionReport->homework()->first();
        $this->game = $homework?->practice_game ?? collect($this->games)->firstWhere('fits', true)['key'] ?? null;
        $this->practiceOnly = $homework === null || $homework->accepts === 'practice';
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::homework.game.page', ['student' => $this->studentName()]);
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function studentName(): string
    {
        return explode(' ', trim((string) DB::table('users')->where('id', $this->sessionReport->student_id)->value('name')))[0];
    }

    /** "After the lesson of …, the report was sent". */
    public function kicker(): string
    {
        $when = $this->sessionReport->session_starts_at->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('l j F');

        return __('learning::homework.game.kicker', ['when' => $when]).' · '.__('learning::reports.statuses.'.$this->sessionReport->status);
    }

    /** @return list<array{title: string, outcome: ?string}> what the report says was taught */
    #[Computed]
    public function taught(): array
    {
        return array_values(array_map(fn (array $i) => ['title' => $i['title'], 'outcome' => $i['outcome']], app(ReportFormQuery::class)->items($this->sessionReport)));
    }

    /** @return list<PracticeItem> the practice of the report's lessons */
    #[Computed]
    public function items(): array
    {
        $nodes = $this->sessionReport->items()->pluck('node_id')->all();

        return array_merge(...array_values(app(CurriculumApi::class)->practiceItems($nodes) ?: [[]]));
    }

    /** @return list<array{key: string, title: string, summary: string, letter: string, fits: bool, items: int}> the games for this student's look */
    #[Computed]
    public function games(): array
    {
        $stage = app(PeopleApi::class)->studentProfile($this->sessionReport->student_id)->stage->value;

        return array_values(array_map(function (CatalogGame $g) {
            $n = count(array_filter($this->items, fn (PracticeItem $i) => $g->plays($i->kind)));

            return ['key' => $g->key, 'title' => $g->title(), 'summary' => $g->summary(), 'letter' => mb_substr($g->title(), 0, 1), 'fits' => $n >= $g->minItems, 'items' => min($n, $g->maxItems)];
        }, array_filter(app(GameCatalog::class)->games(), fn (CatalogGame $g) => $g->fits($stage))));
    }

    /** @return array{game: string, correct: int, total: int, stars: int, rounds: int, at: mixed}|null the student's last round, and how often they played this week */
    #[Computed]
    public function last(): ?array
    {
        $rounds = PracticeRoundEntry::query()->where('student_id', $this->sessionReport->student_id)->latest('played_at');
        $last = (clone $rounds)->first();
        if ($last === null) {
            return null;
        }

        return ['game' => app(GameCatalog::class)->game($last->game)?->title() ?? $last->game, 'correct' => $last->correct, 'total' => $last->total, 'stars' => $last->stars,
            'rounds' => (clone $rounds)->where('played_at', '>=', now()->subDays(7))->count(), 'at' => $last->played_at];
    }

    public function pick(string $key): void
    {
        $this->game = $key;
    }

    /** Try the chosen game with this lesson's practice, as the student will get it. */
    public function tryIt(): void
    {
        $game = $this->game === null ? null : app(GameCatalog::class)->game($this->game);
        if ($game === null) {
            return;
        }
        $items = array_values(array_filter($this->items, fn (PracticeItem $i) => $game->plays($i->kind)));
        $ar = app()->getLocale() === 'ar';
        $this->trying = [
            'key' => $game->key, 'title' => $game->title(), 'how' => $game->howToPlay(),
            'src' => route('games.play', $game->versionId),
            'items' => array_map(fn (PracticeItem $i) => $i->forRound(), array_slice($items, 0, $game->maxItems)),
            'stage' => app(PeopleApi::class)->studentProfile($this->sessionReport->student_id)->stage->value,
            'locale' => $ar ? 'ar' : 'en', 'dir' => $ar ? 'rtl' : 'ltr',
        ];
    }

    public function stopTrying(): void
    {
        $this->trying = null;
    }

    public function send(): void
    {
        try {
            app(ChoosePracticeGame::class)->handle($this->sessionReport, (string) auth()->id(), (string) $this->game, $this->practiceOnly, $this->beforeNext);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        } catch (AuthorizationException) {
            abort(403);
        }
        Notification::make()->success()->title(__('learning::homework.game.sent', ['student' => $this->studentName()]))->send();
        $this->dispatch('homework-game-sent');
    }
}
