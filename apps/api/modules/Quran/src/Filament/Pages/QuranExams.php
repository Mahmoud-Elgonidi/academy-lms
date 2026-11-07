<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Quran\Actions\Certificates\IssueQuranCertificate;
use Modules\Quran\Actions\Exams\BookQuranExam;
use Modules\Quran\Actions\Exams\CancelQuranExam;
use Modules\Quran\Exams\ExaminerCheck;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Filament\Concerns\Refusals;
use Modules\Quran\Http\V1\Resources\ExamPresenter;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\LocalTimeResolver;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * Quran › Exams (docs/architecture/02, section 26): the exams waiting to be
 * booked, those booked (with their examiner and time) and the results.
 * Booking picks an examiner the academy's choice allows (ExaminerCheck) and
 * a day and time on the viewer's clock (LocalTimeResolver), through
 * BookQuranExam; cancelling needs a reason (CancelQuranExam). An examiner
 * records their own exam's result on its own page.
 */
final class QuranExams extends Page
{
    use QuranMenu;

    public const TABS = ['suggested', 'booked', 'results'];

    public const DURATIONS = [20, 30, 45, 60, 90];

    protected string $view = 'quran::filament.admin.exams';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 404;

    protected static ?string $slug = 'quran/exams';

    #[Url]
    public string $tab = 'suggested';

    public static function canAccess(): bool
    {
        return Gate::any([Ability::ViewQuranBoard->value, Ability::ExamineQuran->value]);
    }

    public static function mayBook(): bool
    {
        return Gate::any([Ability::ManageQuran->value, Ability::ExamineQuran->value]);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.exams.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = QuranExam::query()->where('status', 'suggested')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.exams.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.exams.subtitle').' '.ZoneHint::yours();
    }

    public function mount(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'suggested';
    }

    /** @return array{rows: list<array<string, mixed>>, counts: array<string, int>, enabled: bool} */
    #[Computed]
    public function exams(): array
    {
        $present = app(ExamPresenter::class);
        $statuses = ['suggested' => ['suggested'], 'booked' => ['booked'], 'results' => ['passed', 'failed']][$this->tab] ?? ['suggested'];
        $query = QuranExam::query()->with('program')->whereIn('status', $statuses);
        $query = match ($this->tab) {
            'results' => $query->orderByDesc('recorded_at')->limit(100),
            'booked' => $query->orderBy('booked_at'),
            default => $query->orderBy('created_at'),
        };
        $me = (string) auth()->id();
        $exams = $query->get();
        $certified = QuranCertificate::query()->whereIn('exam_id', $exams->pluck('id')->all())->pluck('number', 'exam_id');
        $rows = $exams->map(fn (QuranExam $e) => $present->exam($e) + [
            'certificate' => $certified[$e->id] ?? null,
            'mine' => $e->examiner_id === $me && $e->status === 'booked',
            'result_url' => $e->examiner_id === $me && $e->status === 'booked' ? QuranExamResult::getUrl(['exam' => $e->id]) : null,
        ])->all();
        if ($this->tab === 'booked') {
            usort($rows, fn (array $a, array $b) => ($a['lesson']['starts_at']['utc'] ?? '') <=> ($b['lesson']['starts_at']['utc'] ?? ''));
        }
        $counts = QuranExam::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'rows' => $rows,
            'counts' => ['suggested' => (int) ($counts['suggested'] ?? 0), 'booked' => (int) ($counts['booked'] ?? 0),
                'results' => (int) ($counts['passed'] ?? 0) + (int) ($counts['failed'] ?? 0)],
            'enabled' => QuranSettings::current()->exams_enabled,
        ];
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'suggested';
        unset($this->exams);
    }

    public function bookAction(): Action
    {
        $w = 'quran::screens.exams.';

        return Action::make('book')->label(__($w.'book'))->icon(Heroicon::OutlinedCalendarDays)->size('sm')
            ->authorize(fn () => self::mayBook())
            ->slideOver()->modalWidth(Width::Medium)
            ->modalHeading(fn (array $arguments) => __($w.'book_heading', ['stop' => $this->exam($arguments)?->program->name ?? '']))
            ->modalDescription(fn (array $arguments) => ($e = $this->exam($arguments)) === null ? null : app(ExamPresenter::class)->exam($e)['stop']['label'].' · '.app(ExamPresenter::class)->exam($e)['student'])
            ->modalSubmitActionLabel(__($w.'book_submit'))
            ->fillForm(fn (array $arguments) => [
                'date' => max(CarbonImmutable::now()->setTimezone($this->zone())->addDay()->toDateString(), $this->exam($arguments)?->not_before?->toDateString() ?? ''),
                'time' => '16:00', 'duration_min' => 30,
            ])
            ->schema(fn (array $arguments) => [
                Select::make('examiner_id')->label(__('quran::exams.attributes.examiner_id'))->required()->native(false)->searchable()
                    ->options(fn () => $this->examiners($arguments))->helperText(__($w.'examiner_hint.'.QuranSettings::current()->examiner)),
                Grid::make(2)->schema([
                    DatePicker::make('date')->label(__($w.'date'))->required()->native(false)->minDate(CarbonImmutable::now()->setTimezone($this->zone())->toDateString())
                        ->displayFormat('j M Y')->closeOnDateSelection(),
                    TimePicker::make('time')->label(__($w.'time'))->required()->seconds(false)->native(true),
                ]),
                Select::make('duration_min')->label(__('quran::exams.attributes.duration_min'))->required()->native(false)
                    ->options(collect(self::DURATIONS)->mapWithKeys(fn (int $m) => [$m => trans_choice($w.'minutes', $m, ['n' => $m])])->all()),
            ])
            ->action(function (array $data, array $arguments) use ($w) {
                $exam = $this->exam($arguments) ?? abort(404);
                $at = app(LocalTimeResolver::class)->resolve((string) $data['date'], substr((string) $data['time'], 0, 5), $this->zone())->utc;
                Refusals::under(fn () => app(BookQuranExam::class)->handle($exam, (string) $data['examiner_id'], $at, (int) $data['duration_min'], (string) auth()->id()),
                    ['examiner_id', 'duration_min', 'starts_at' => 'date', 'teacher' => 'examiner_id']);
                unset($this->exams);
                Notification::make()->success()->title(__($w.'booked'))->send();
            });
    }

    public function cancelAction(): Action
    {
        $w = 'quran::screens.exams.';

        return Action::make('cancel')->label(__($w.'cancel'))->icon(Heroicon::OutlinedXMark)->size('sm')->color('danger')->link()
            ->authorize(fn () => self::mayBook())
            ->modalHeading(__($w.'cancel_heading'))->modalDescription(__($w.'cancel_hint'))->modalSubmitActionLabel(__($w.'cancel_submit'))
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)->modalIconColor('danger')->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__('quran::exams.attributes.reason'))->required()->minLength(3)->maxLength(300)->rows(3)])
            ->action(function (array $data, array $arguments) use ($w) {
                $exam = $this->exam($arguments) ?? abort(404);
                Refusals::under(fn () => app(CancelQuranExam::class)->handle($exam, (string) auth()->id(), (string) $data['reason']), ['reason']);
                unset($this->exams);
                Notification::make()->success()->title(__($w.'cancelled'))->send();
            });
    }

    /** A passed exam the academy has not given its certificate yet (certificates were off then): issue it now. */
    public function issueAction(): Action
    {
        $w = 'quran::screens.exams.';

        return Action::make('issue')->label(__($w.'issue'))->icon(Heroicon::OutlinedTrophy)->size('sm')->color('success')->link()
            ->authorize(fn () => Gate::allows(Ability::ManageQuran->value))
            ->visible(fn (array $arguments) => ($e = $this->exam($arguments)) !== null && $e->status === 'passed' && $e->program->counts_for_certificate
                && QuranSettings::current()->certificates_enabled && ! QuranCertificate::query()->where('exam_id', $e->id)->exists())
            ->requiresConfirmation()->modalHeading(__($w.'issue_heading'))->modalDescription(__($w.'issue_hint'))
            ->action(function (array $arguments) use ($w) {
                $certificate = app(IssueQuranCertificate::class)->handle($this->exam($arguments) ?? abort(404), (string) auth()->id());
                unset($this->exams);
                Notification::make()->success()->title(__($w.'issued', ['number' => $certificate->number]))->send();
            });
    }

    /**
     * Who may examine this exam, by the academy's choice (ExaminerCheck), by name.
     *
     * @return array<string, string>
     */
    public function examiners(array $arguments): array
    {
        $exam = $this->exam($arguments);
        if ($exam === null) {
            return [];
        }
        $identity = app(IdentityApi::class);
        $ids = array_values(array_unique([...$identity->usersWithAbility(Ability::ExamineQuran), ...$identity->usersWithAbility(Ability::TeachQuran)]));
        $check = app(ExaminerCheck::class);
        $allowed = array_values(array_filter($ids, function (string $id) use ($check, $exam) {
            try {
                $check->assert($exam, $id);

                return true;
            } catch (ValidationException) {
                return false;
            }
        }));

        return DB::table('users')->whereIn('id', $allowed)->orderBy('name')->pluck('name', 'id')->all();
    }

    private function exam(array $arguments): ?QuranExam
    {
        $id = (string) ($arguments['exam'] ?? '');

        return Str::isUuid($id) ? QuranExam::query()->with('program', 'plan')->find($id) : null;
    }

    private function zone(): string
    {
        return app(ViewerTimezone::class)->effective();
    }
}
