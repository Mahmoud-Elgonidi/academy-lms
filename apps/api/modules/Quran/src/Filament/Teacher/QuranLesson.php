<?php

namespace Modules\Quran\Filament\Teacher;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Quran\Actions\Lessons\RecordQuranLesson;
use Modules\Quran\Actions\Mushaf\TurnMushafPage;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\Grading;
use Modules\Quran\Domain\Programs\Portion;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Models\RecitationMistake;
use Modules\Quran\Mushaf\MushafLessons;
use Modules\Quran\Programs\Mushaf;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\Amounts;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * «حصة قرآن» for its teacher (a substitute too; docs/architecture/02,
 * section 26): today's portion from the plan in three cards (new, near
 * review, far review) with their verses and pages, a way to open the
 * lesson's shared mushaf on each, quick counters of mistakes by kind for
 * each student (starting from the words marked in the mushaf), the grade
 * each part gets worked out as they count (Domain\Grading) or the
 * teacher's own with a note, and whether the plan will move on or the new
 * part comes back. Recorded once through RecordQuranLesson; then the result.
 */
final class QuranLesson extends Page
{
    protected string $view = 'quran::filament.teacher.lesson';

    protected static ?string $slug = 'quran/lessons/{session}';

    protected static bool $shouldRegisterNavigation = false;

    public string $session = '';

    /** The student shown (a group lesson has several). */
    public string $student = '';

    /** @var array<string, array<string, array{on: bool, mistakes: array<string, int>, grade: string, note: string}>> student => part => sheet */
    public array $sheet = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::TeachQuran->value);
    }

    public function mount(string $session): void
    {
        $card = Str::isUuid($session) ? app(AttendanceApi::class)->lesson($session) : null;
        abort_unless($card !== null && $card->teacherId === auth()->id(), 404);
        $this->session = $card->id;
        $this->student = $card->studentIds()[0] ?? '';
        $this->prepareSheet();
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.lesson.title');
    }

    public function getSubheading(): ?string
    {
        $card = $this->card();

        return $card->subject().' · '.$card->startsAt->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat(Clock::pattern('l j F، H:i'));
    }

    public function getBreadcrumbs(): array
    {
        return [QuranLessons::getUrl() => __('quran::screens.lessons.title'), __('quran::screens.lesson.crumb')];
    }

    private function card(): LessonCard
    {
        return app(AttendanceApi::class)->lesson($this->session) ?? abort(404);
    }

    private function plan(): ?QuranPlan
    {
        return app(Plans::class)->forLesson($this->card());
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function lesson(): array
    {
        $card = $this->card();
        $plans = app(Plans::class);
        $plan = $this->plan();
        $portion = $plan === null ? null : $plans->portion($plan);
        $present = app(ProgramPresenter::class);
        $students = $card->students !== [] ? array_map(fn (array $s) => ['id' => $s['id'], 'name' => $s['name']], $card->students)
            : [['id' => (string) $card->studentId, 'name' => $card->studentName]];
        $results = QuranLessonResult::query()->where('session_id', $card->id)->get()->keyBy('student_id');
        $now = CarbonImmutable::now();
        $settings = QuranSettings::current();
        $mushaf = app(MushafLessons::class)->for($card->id, (string) auth()->id());

        $grades = [];
        foreach ($this->sheet as $studentId => $parts) {
            foreach ($parts as $part => $s) {
                $grades[$studentId][$part] = $portion === null ? null : $this->gradeOf($portion, $part, $s, $settings);
            }
        }
        $shape = $plan?->shape();
        $sheet = $this->sheet[$this->student] ?? [];
        $newGrade = $grades[$this->student]['new']['grade'] ?? null;

        return [
            'students' => $students,
            'plan' => $plan === null ? null : $present->plan($plan, $portion, $plans->frozen($plan, $card->startsAt)),
            'pace' => $shape === null ? null : Amounts::pace($shape->newAmount, $shape->unit, $shape->nearPortions, $shape->farPages),
            'pass_grade' => $shape?->passGrade,
            'types' => $present->mistakeTypes(),
            'grades' => $grades,
            'results' => $results->map(fn (QuranLessonResult $r) => $present->result($r))->all(),
            'open' => $card->status !== 'cancelled' && $now->greaterThanOrEqualTo($card->startsAt->subMinutes(MushafLessons::OPENS_BEFORE_MIN)),
            'opens_at' => $card->startsAt->subMinutes(MushafLessons::OPENS_BEFORE_MIN)->setTimezone(app(ViewerTimezone::class)->effective())
                ->locale(app()->getLocale())->translatedFormat(Clock::pattern('l، H:i')),
            'cancelled' => $card->status === 'cancelled',
            'mushaf' => $mushaf !== null,
            'class_url' => route('classroom.page', ['session' => $card->id]),
            // What happens to the plan with the new part as it stands now (the student shown).
            'outcome' => $portion === null || ! $portion->has('new') || ! ($sheet['new']['on'] ?? false) || $newGrade === null ? null
                : (Grades::meets($newGrade, (string) $shape?->passGrade) ? 'advance' : 'repeat'),
            'next_title' => $plan === null || $portion?->next === null ? null : $this->positionTitle($portion),
            'student_url' => $card->enrollmentId === null ? null : StudentHifz::getUrl(['enrollment' => $card->enrollmentId]),
        ];
    }

    public function selectStudent(string $id): void
    {
        if (isset($this->sheet[$id])) {
            $this->student = $id;
        }
        unset($this->lesson);
    }

    public function bump(string $part, string $kind, int $by): void
    {
        if (! isset($this->sheet[$this->student][$part]['mistakes'][$kind])) {
            return;
        }
        $n = (int) $this->sheet[$this->student][$part]['mistakes'][$kind];
        $this->sheet[$this->student][$part]['mistakes'][$kind] = max(0, min(200, $n + ($by >= 0 ? 1 : -1)));
        $this->sheet[$this->student][$part]['on'] = true;
        unset($this->lesson);
    }

    public function togglePart(string $part): void
    {
        if (isset($this->sheet[$this->student][$part])) {
            $this->sheet[$this->student][$part]['on'] = ! $this->sheet[$this->student][$part]['on'];
        }
        unset($this->lesson);
    }

    public function updatedSheet(): void
    {
        unset($this->lesson);
    }

    /** Turns the lesson's shared mushaf to the part's first page, then opens the class page beside this one. */
    public function openMushaf(string $part): void
    {
        $lesson = app(MushafLessons::class)->for($this->session, (string) auth()->id());
        $plan = $this->plan();
        $page = $plan === null ? null : (app(Plans::class)->portion($plan)?->pagesOf($part)[0] ?? null);
        if ($lesson !== null && $page !== null) {
            app(TurnMushafPage::class)->handle($lesson, $page);
        }
        $this->js('window.open('.json_encode(route('classroom.page', ['session' => $this->session])).", '_blank', 'noopener')");
    }

    public function record(): void
    {
        $card = $this->card();
        abort_unless($card->teacherId === auth()->id(), 403);
        $this->resetErrorBag();
        $students = [];
        $order = [];
        foreach ($this->sheet as $studentId => $parts) {
            $given = [];
            foreach ($parts as $part => $s) {
                if (! $s['on']) {
                    continue;
                }
                $given[$part] = ['mistakes' => array_map('intval', $s['mistakes'])] + array_filter(['grade' => $s['grade'] ?: null, 'note' => trim($s['note']) ?: null]);
            }
            if ($given !== [] && ! QuranLessonResult::query()->where('session_id', $card->id)->where('student_id', $studentId)->exists()) {
                $students[] = ['student_id' => (string) $studentId, 'parts' => $given];
                $order[] = (string) $studentId;
            }
        }
        if ($students === []) {
            throw ValidationException::withMessages(['sheet' => __('quran::screens.lesson.nothing')]);
        }
        try {
            app(RecordQuranLesson::class)->handle($card->id, (string) auth()->id(), ['students' => $students]);
        } catch (ValidationException $e) {
            // students.{i}.parts.{part}.{field} → sheet.{student}.{part}.{field}, shown under the field.
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(function (array $m, string $key) use ($order) {
                if (preg_match('/^students\.(\d+)\.parts\.(new|near|far)\.(note|grade)$/', $key, $x) === 1 && isset($order[(int) $x[1]])) {
                    $this->student = $order[(int) $x[1]];

                    return ["sheet.{$order[(int) $x[1]]}.{$x[2]}.{$x[3]}" => $m];
                }

                return [$key => $m];
            })->all());
        }
        unset($this->lesson);
        Notification::make()->success()->title(__('quran::screens.lesson.recorded'))->send();
    }

    /** Each student's sheet: the parts in today's portion, their mistakes from the words marked in the mushaf. */
    private function prepareSheet(): void
    {
        $plan = $this->plan();
        $portion = $plan === null ? null : app(Plans::class)->portion($plan);
        $kinds = QuranSettings::current()->mistakeKinds();
        foreach ($this->card()->studentIds() as $studentId) {
            foreach (RecordQuranLesson::PARTS as $part) {
                if ($portion === null || ! $portion->has($part)) {
                    continue;
                }
                $marked = $this->marks($studentId, $portion->part($part));
                $this->sheet[$studentId][$part] = [
                    'on' => true,
                    'mistakes' => collect($kinds)->mapWithKeys(fn (string $k) => [$k => (int) ($marked[$k] ?? 0)])->all(),
                    'grade' => '',
                    'note' => '',
                ];
            }
        }
    }

    /**
     * @param  list<VerseRange>  $ranges
     * @return array<string, int>
     */
    private function marks(string $studentId, array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        return RecitationMistake::query()->where('session_id', $this->session)->where('student_id', $studentId)
            ->where(function ($q) use ($ranges) {
                foreach ($ranges as $range) {
                    $q->orWhere(fn ($r) => $r->where('surah', $range->surah)->whereBetween('verse', [$range->from, $range->to]));
                }
            })
            ->groupBy('kind')->selectRaw('kind, count(*) as n')->pluck('n', 'kind')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * @param  array{on: bool, mistakes: array<string, int>, grade: string, note: string}  $s
     * @return array{grade: string, computed: string, penalty: float}
     */
    private function gradeOf(Portion $portion, string $part, array $s, QuranSettings $settings): array
    {
        $ranges = $portion->part($part);
        $pages = $ranges === [] ? Grading::MIN_PAGES : (float) app(Mushaf::class)->layout()?->pagesIn($ranges);
        $computed = Grading::grade(array_filter(array_map('intval', $s['mistakes'])), $settings->weights(), $settings->gradeScale(), $pages);

        return ['grade' => $s['grade'] !== '' ? $s['grade'] : $computed['grade'], 'computed' => $computed['grade'], 'penalty' => $computed['penalty']];
    }

    private function positionTitle(Portion $portion): ?string
    {
        $next = $portion->next;
        if ($next === null) {
            return null;
        }
        if ($next->isLesson()) {
            return __('quran::programs.noorani_lesson', ['number' => $next->verse, 'title' => ((array) trans('quran::tracks.noorani.lessons'))[$next->verse - 1] ?? '']);
        }

        return app(ProgramPresenter::class)->range(VerseRange::of((int) $next->surah, $next->verse, $next->verse))['title'];
    }
}
