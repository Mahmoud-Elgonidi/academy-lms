<?php

namespace Modules\Learning\Filament\Family;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Learning\Actions\MarkCheersSeen;
use Modules\Learning\Actions\OpenWeeklyChest;
use Modules\Learning\Actions\SendCheer;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\Cheer;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The learner's journey in the family area (docs/architecture/02, section 18):
 * a student's home (JourneyWidget), and the journey page of guardians and
 * adults (FamilyJourney). The same
 * answer as the apps' (JourneyMap), in the viewer's look: a child's map of
 * islands (the course's levels) with their stations (its lessons), middle
 * school's levels with the race against yourself, the calm view for older
 * students, adults and an academy that chose it, and a guardian's view of
 * their child. The rounds and the eggs are the games' (they plug into the
 * "learning::journey.play" and "learning::journey.guardian" hooks and talk to
 * this page by browser events): without them the journey stays whole.
 */
final class JourneyBoard extends Component
{
    /** Where the games put their rounds, results and eggs (Games' family plugin fills it). */
    public const PLAY_HOOK = 'learning::journey.play';

    /** Where the games put a guardian's limits and glance at the week. */
    public const GUARDIAN_HOOK = 'learning::journey.guardian';

    /** The course on screen (its enrollment), when the learner has several. */
    public ?string $course = null;

    /** A homework's round to start on arrival (?homework=, from the homework page and old links): game and homework. */
    public ?array $start = null;

    public function mount(): void
    {
        $homework = request()->query('homework');
        if (! is_string($homework) || $homework === '') {
            return;
        }
        $mission = collect($this->journey['missions'] ?? [])->firstWhere('homework_id', $homework);
        if ($mission === null) {
            return;
        }
        $this->course = $mission['enrollment_id'] ?? $this->course;
        $game = $this->gameFor($mission);
        if ($game !== null && $mission['state'] !== 'done') {
            $this->start = ['game' => $game, 'homework' => $homework];
        }
    }

    public function learner(): ?Learner
    {
        return FamilyLearning::learner();
    }

    public function isSelf(): bool
    {
        return $this->learner()?->id === (string) auth()->id();
    }

    /** The learner's first name, for "Yousef's journey". */
    public function firstName(): string
    {
        $name = trim((string) $this->learner()?->name);

        return explode(' ', $name)[0] ?: $name;
    }

    /** @return array<string, mixed>|null JourneyMap's answer for the learner on screen */
    #[Computed]
    public function journey(): ?array
    {
        $learner = $this->learner();
        if ($learner === null) {
            return null;
        }
        $stage = app(FamilyView::class)->stage($learner->id)->value;
        $journey = app(JourneyMap::class)->for($learner->id, $stage, ! $this->isSelf());
        // The games' layer as plain arrays, like the rest of the answer.
        $journey['games'] = $journey['games'] === null ? null : get_object_vars($journey['games']);

        return $journey;
    }

    /**
     * The look of the screen: guardian (a guardian looking at their child), calm (older students, adults, and
     * everyone when the academy chose the calm or plain style), else the student's own stage (kids, middle).
     */
    public function look(): string
    {
        $j = $this->journey;
        if (! $this->isSelf()) {
            return 'guardian';
        }
        if ($j === null || $j['style'] !== 'playful') {
            return 'calm';
        }

        return match (FamilyLearning::stage()) {
            LearnerStage::Kids => 'kids',
            LearnerStage::Middle => 'middle',
            default => 'calm',
        };
    }

    /** No stars, chest, egg or games when the academy chose the plain style. */
    public function plain(): bool
    {
        return ($this->journey['style'] ?? null) === 'plain';
    }

    /** @return array<string, mixed>|null the course on screen */
    public function currentCourse(): ?array
    {
        $courses = $this->journey['courses'] ?? [];

        return collect($courses)->firstWhere('enrollment_id', $this->course)
            ?? collect($courses)->first(fn (array $c) => $c['has_curriculum'] && ! $c['finished'])
            ?? $courses[0] ?? null;
    }

    public function chooseCourse(string $enrollmentId): void
    {
        if (collect($this->journey['courses'] ?? [])->contains('enrollment_id', $enrollmentId)) {
            $this->course = $enrollmentId;
        }
    }

    /** @return array<string, array<string, mixed>> every station of the learner's courses by id, with its island and course */
    #[Computed]
    public function stations(): array
    {
        $out = [];
        foreach ($this->journey['courses'] ?? [] as $course) {
            foreach ($course['islands'] as $island) {
                foreach ($island['stations'] as $station) {
                    $out[$station['id']] = $station + ['island_id' => $island['id'], 'island_art' => $island['art']['key'] ?? null, 'enrollment_id' => $course['enrollment_id']];
                }
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> the missions of a station that are still to do */
    public function missionsOn(string $stationId): array
    {
        return array_values(array_filter($this->journey['missions'] ?? [], fn (array $m) => $m['station_id'] === $stationId && $m['state'] !== 'done'));
    }

    /** The game a homework plays: the one its teacher chose, else the first that fits its lesson. */
    public function gameFor(array $mission): ?string
    {
        if ($mission['kind'] !== 'homework' || ! $mission['practice'] || ($this->journey['games'] ?? null) === null) {
            return null;
        }
        $games = $mission['station_id'] === null ? [] : ($this->stations[$mission['station_id']]['games'] ?? []);

        return $mission['game'] ?? ($games[0]['key'] ?? null);
    }

    /** A time as the API writes it, in the viewer's zone. */
    public function at(?CarbonInterface $instant): string
    {
        return app(LocalTimePresenter::class)->present($instant)['label'] ?? '';
    }

    /** A day on the viewer's clock ("Friday 20 November"), for when a course should finish. */
    public function day(?CarbonInterface $instant): string
    {
        return $instant === null ? '' : FamilyLearning::date(CarbonImmutable::instance($instant), app(ViewerTimezone::class)->effective());
    }

    /** "in 2 days", for the lesson's boat. */
    public function until(CarbonInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->locale(app()->getLocale())->diffForHumans(['parts' => 1, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]);
    }

    /** The lesson's way in (the class page, by route name), once its classroom is open. */
    public function joinUrl(array $mission): ?string
    {
        return ($mission['join']['open'] ?? false) && Route::has('classroom.page')
            ? route('classroom.page', ['session' => $mission['lesson_id']]) : null;
    }

    /** The album of creatures (the games' page, by route name), when the academy has games. */
    public function albumUrl(?string $island = null): ?string
    {
        return ($this->journey['games'] ?? null) !== null && Route::has('filament.family.pages.games.creatures')
            ? route('filament.family.pages.games.creatures', array_filter(['island' => $island])) : null;
    }

    /** The viewer's own channel: practice, homework and lessons of the learner they follow are signalled there. */
    public function channel(): string
    {
        return PersonalChannel::name((string) auth()->id());
    }

    /** When the clock alone changes the screen (a classroom opens or closes), for LmsLive (ms). */
    public function liveNext(): ?int
    {
        $now = CarbonImmutable::now();
        $closes = (int) config('classroom.closes_after_min', 10);
        $next = collect($this->journey['missions'] ?? [])->where('kind', 'lesson')
            ->map(fn (array $m) => $m['join']['open'] ? ($m['ends_at'] ?? $m['starts_at'])->addMinutes($closes) : $m['join']['opens_at'])
            ->filter(fn (CarbonInterface $t) => $t->greaterThan($now))->min();

        return $next?->getTimestampMs();
    }

    /** @return list<array{kind: string, from: string}> cheers the student has not seen yet */
    #[Computed]
    public function cheers(): array
    {
        $learner = $this->learner();
        if ($learner === null || ! $this->isSelf()) {
            return [];
        }
        $cheers = Cheer::query()->where('student_id', $learner->id)->whereNull('seen_at')->orderBy('created_at')->limit(10)->get();
        $names = DB::table('users')->whereIn('id', $cheers->pluck('from_id')->unique())->pluck('name', 'id');

        return $cheers->map(fn (Cheer $c) => ['kind' => $c->kind, 'from' => strtok((string) ($names[$c->from_id] ?? ''), ' ') ?: ''])->all();
    }

    /** For a guardian: cheers they may still send the learner today. */
    public function cheersLeft(): int
    {
        $learner = $this->learner();

        return $learner === null || $this->isSelf() ? 0 : max(0, ChallengeCatalog::CHEERS_PER_DAY - Cheer::query()->where('from_id', (string) auth()->id())
            ->where('student_id', $learner->id)->where('created_at', '>', now()->subDay())->count());
    }

    /** The student opens the week's chest: its stars, and the kids' next sticker. */
    public function openChest(string $period): void
    {
        $learner = $this->learner();
        abort_unless($learner !== null && $this->isSelf(), 403);
        try {
            $r = app(OpenWeeklyChest::class)->handle($learner->id, FamilyLearning::stage()->value, $period);
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        Notification::make()->success()->title(__('learning::map.chest.got', ['stars' => $r['stars']]))
            ->body($r['sticker'] ? __('learning::map.chest.sticker', ['name' => __('learning::challenges.stickers.'.$r['sticker'])]) : null)->send();
        $this->dispatch('journey-chest-opened', stars: $r['stars']);
        $this->refreshJourney();
    }

    /** A guardian cheers the learner on screen. */
    public function cheer(string $kind): void
    {
        $learner = $this->learner();
        abort_unless($learner !== null && ! $this->isSelf(), 403);
        try {
            app(SendCheer::class)->handle((string) auth()->id(), $learner->id, $kind);
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        Notification::make()->success()->title(__('learning::map.cheers.sent', ['name' => $this->firstName()]))->send();
    }

    /** The student saw their cheers. */
    #[Renderless]
    public function cheersSeen(): void
    {
        $learner = $this->learner();
        abort_unless($learner !== null && $this->isSelf(), 403);
        app(MarkCheersSeen::class)->handle($learner->id);
    }

    /** A round finished, an egg hatched, a limit changed (the games' component), or a signal came: read the journey again. */
    #[On('journey-changed')]
    public function refreshJourney(): void
    {
        unset($this->journey, $this->stations, $this->cheers);
    }

    public function render(): View
    {
        return view('learning::filament.family.map.board');
    }
}
