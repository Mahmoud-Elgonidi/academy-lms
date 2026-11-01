<?php

namespace Modules\Quran\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Modules\Quran\Actions\Exams\RecordQuranExam;
use Modules\Quran\Domain\Exams\ExamOutcome;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Grading\Grading;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Filament\Concerns\Refusals;
use Modules\Quran\Http\V1\Resources\ExamPresenter;
use Modules\Quran\Http\V1\Resources\ProgramPresenter;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Models\RecitationMistake;
use Modules\SharedKernel\Authorization\Ability;

/**
 * An examiner records their exam's result (docs/architecture/02, section
 * 26), in the back office or the teachers' area: the mistakes counted by
 * kind (from the words marked in the exam lesson's mushaf, changeable), the
 * grade they give worked out as they count (Domain\Grading over the stop's
 * pages), their own grade with a note, and for a fail what to work on and
 * the day to try again. Through RecordQuranExam alone, once; then the result.
 */
final class QuranExamResult extends Page
{
    use QuranMenu;

    protected string $view = 'quran::filament.admin.exam-result';

    protected static ?string $slug = 'quran/exams/{exam}/result';

    protected static bool $shouldRegisterNavigation = false;

    public string $exam = '';

    /** @var array<string, int> */
    public array $counts = [];

    public string $grade = '';

    public string $note = '';

    public string $retakeOn = '';

    public static function canAccess(): bool
    {
        return Gate::any([Ability::ExamineQuran->value, Ability::TeachQuran->value]);
    }

    public function mount(string $exam): void
    {
        $found = Str::isUuid($exam) ? QuranExam::query()->with('program')->find($exam) : null;
        abort_unless($found !== null && $found->examiner_id === auth()->id() && in_array($found->status, ['booked', 'passed', 'failed'], true), 404);
        $this->exam = $found->id;
        $marked = RecitationMistake::query()->where('session_id', $found->session_id)->where('student_id', $found->student_id)
            ->groupBy('kind')->selectRaw('kind, count(*) as n')->pluck('n', 'kind');
        $this->counts = collect(QuranSettings::current()->mistakeKinds())->mapWithKeys(fn (string $k) => [$k => (int) ($marked[$k] ?? 0)])->all();
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.result.title');
    }

    public function getBreadcrumbs(): array
    {
        return Filament::getCurrentPanel()?->getId() === 'academy' ? [QuranExams::getUrl() => __('quran::screens.exams.title'), __('quran::screens.result.crumb')] : [];
    }

    private function found(): QuranExam
    {
        return QuranExam::query()->with('program')->findOrFail($this->exam);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function result(): array
    {
        $exam = $this->found();
        $settings = QuranSettings::current();
        $pages = app(Stops::class)->pages($exam->program, $exam->stop_kind, $exam->stop_number);
        $computed = Grading::grade(array_filter(array_map('intval', $this->counts)), $settings->weights(), $settings->gradeScale(), $pages);
        $decided = Grading::decide($computed['grade'], $this->grade === '' ? null : $this->grade, $this->note);
        $grade = $decided['error'] === null ? $decided['grade'] : ($this->grade !== '' ? $this->grade : $computed['grade']);

        return [
            'exam' => app(ExamPresenter::class)->exam($exam),
            'recorded' => $exam->status !== 'booked',
            'pages' => $pages,
            'pass_grade' => $exam->program->pass_grade,
            'types' => app(ProgramPresenter::class)->mistakeTypes(),
            'computed' => $computed,
            'grade' => $grade,
            'passed' => ExamOutcome::passed($grade, $exam->program->pass_grade),
            'overridden' => $this->grade !== '' && $this->grade !== $computed['grade'],
            'grades' => Grades::ORDER,
            'class_url' => $exam->session_id === null ? null : route('classroom.page', ['session' => $exam->session_id]),
            'retake_max' => CarbonImmutable::now()->addDays(ExamOutcome::RETAKE_MAX_DAYS)->toDateString(),
        ];
    }

    public function bump(string $kind, int $by): void
    {
        if (array_key_exists($kind, $this->counts)) {
            $this->counts[$kind] = max(0, min(500, (int) $this->counts[$kind] + ($by >= 0 ? 1 : -1)));
        }
        unset($this->result);
    }

    public function updated(): void
    {
        unset($this->result);
    }

    public function record(): void
    {
        $this->resetErrorBag();
        $exam = $this->found();
        abort_unless($exam->examiner_id === auth()->id(), 403);
        Refusals::under(fn () => app(RecordQuranExam::class)->handle($exam, (string) auth()->id(), [
            'mistakes' => array_map('intval', $this->counts),
            'grade' => $this->grade === '' ? null : $this->grade,
            'note' => $this->note === '' ? null : $this->note,
            'retake_on' => $this->retakeOn === '' ? null : $this->retakeOn,
        ]), ['note' => 'note', 'retake_on' => 'retakeOn', 'grade' => 'grade'], '');
        unset($this->result);
        $exam->refresh();
        Notification::make()->success()->title(__('quran::screens.result.'.($exam->status === 'passed' ? 'passed' : 'failed')))->send();
    }
}
