<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewPlan;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Models\BookingHold;
use Modules\Scheduling\Filament\LiveBoardView;
use Modules\Scheduling\Queries\FindTeachersQuery;
use Modules\Scheduling\Queries\FreeTimesQuery;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\Timezones;

/**
 * "Find a teacher" (docs/architecture/02, section 2): the student's times on
 * a week grid in the student's timezone, teachers ranked by fit, weekly
 * times as chips on the student's clock with the teacher's clock under them,
 * a 10-minute hold, then the enrolment (plan, payer, invoice, weekly times
 * and their sessions). In trial mode: one free lesson in the next two weeks
 * with a teacher who takes trials, ranked by how often their trials became
 * subscriptions; sales may book it. Other screens (the CRM's lead page) open
 * it filled in (?mode, student, subject, tz, days, from, to, return).
 *
 * @property-read Schema $form
 */
final class FindTeacher extends Page
{
    protected string $view = 'scheduling::filament.find-teacher';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = -5;

    protected static ?string $slug = 'find-teacher';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<int|string, list<int>> student weekday => hours chosen on the grid */
    public array $cells = [];

    public bool $searched = false;

    public ?string $pickedTeacher = null;

    /** @var list<string> candidate keys "weekday|HH:MM" on the teacher's clock */
    public array $picked = [];

    public ?string $holdBatch = null;

    public ?int $holdExpiresAt = null;

    /** "free": where teachers are free now, with suggestions; "mine": the hours that suit the student. */
    public string $timesTab = 'free';

    /** Why the last hold or booking was refused, shown beside its button. */
    public ?string $refusal = null;

    /** Where to go after booking (a path in this panel), when another screen sent us here. */
    public ?string $back = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::FindTeachers->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::find.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::find.title');
    }

    public function mount(): void
    {
        $query = request()->query();
        $back = (string) ($query['return'] ?? '');
        $this->back = str_starts_with($back, '/') && ! str_starts_with($back, '//') ? $back : null;
        $this->form->fill([
            'mode' => ($query['mode'] ?? null) === 'trial' ? 'trial' : 'series',
            'student_id' => $query['student'] ?? null,
            'timezone' => in_array($query['tz'] ?? null, Timezones::all(), true) ? $query['tz'] : app(TenantContext::class)->require()->defaultTimezone,
            'subject_id' => $query['subject'] ?? null,
            'gender' => 'any',
            'language' => null,
            'level_id' => null,
            'category_id' => null,
            'age_group' => null,
            'per_week' => min(5, max(1, (int) ($query['per_week'] ?? 2))),
            'duration' => 30,
            'start_date' => CarbonImmutable::now()->addDay()->toDateString(),
            'weeks' => 8,
            'min_coverage' => 8,
        ]);
        if ($this->data['student_id'] && ! isset($query['tz'])) {
            $this->studentChosen($this->data['student_id']);
        }

        // Preferred times sent along: those hours on those days, and the search runs.
        $days = array_values(array_filter(array_map('intval', explode(',', (string) ($query['days'] ?? ''))), fn (int $d) => $d >= 1 && $d <= 7));
        [$from, $to] = [(int) ($query['from'] ?? 0), (int) ($query['to'] ?? 0)];
        if ($days !== [] && $from >= 0 && $to > $from && $to <= 24) {
            foreach ($days as $day) {
                $this->cells[$day] = array_values(array_filter(range($from, $to - 1), fn (int $h) => $h >= 6 && $h <= 23));
            }
            $this->searched = filled($this->data['subject_id']) && array_filter($this->cells) !== [];
            $this->timesTab = 'mine';
        }
    }

    public function isTrial(): bool
    {
        return ($this->data['mode'] ?? 'series') === 'trial';
    }

    public function form(Schema $schema): Schema
    {
        $series = fn (Get $get) => $get('mode') !== 'trial';
        $changed = fn () => $this->resetResults();

        return $schema->statePath('data')->components([
            ToggleButtons::make('mode')->label(__('scheduling::find.fields.mode'))->hiddenLabel()->inline()->required()->live()
                ->options(['series' => __('scheduling::find.modes.series'), 'trial' => __('scheduling::find.modes.trial')])
                ->icons(['series' => Heroicon::OutlinedCalendarDays, 'trial' => Heroicon::OutlinedSparkles])
                ->afterStateUpdated($changed),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                Select::make('student_id')->label(__('scheduling::find.fields.student'))
                    ->options(fn () => self::students())->searchable()->live()
                    ->afterStateUpdated(fn (?string $state) => $this->studentChosen($state)),
                Select::make('subject_id')->label(__('scheduling::find.fields.subject'))
                    ->options(fn () => DB::table('subjects')->orderBy('name_ar')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id'))->required()->native(false)
                    ->live()->afterStateUpdated(function (Set $set) {
                        $set('level_id', null);
                        $set('category_id', null);
                        $this->resetResults();
                    }),
                Select::make('timezone')->label(__('scheduling::find.fields.timezone'))
                    ->options(fn () => array_combine(Timezones::all(), Timezones::all()))->searchable()->required()->live()
                    ->afterStateUpdated($changed),
                Select::make('per_week')->label(__('scheduling::find.fields.per_week'))
                    ->options(array_combine(range(1, 5), array_map(fn ($n) => trans_choice('scheduling::find.per_week', $n, ['n' => $n]), range(1, 5))))->required()->native(false)
                    ->visible($series)->live()->afterStateUpdated($changed),
                ToggleButtons::make('duration')->label(__('scheduling::find.fields.duration'))
                    ->options([30 => '30', 45 => '45', 60 => '60'])->inline()->required()->live()->afterStateUpdated($changed),
                DatePicker::make('start_date')->label(__('scheduling::find.fields.start_date'))->required()->native(false)
                    ->live()->afterStateUpdated($changed),
            ]),
            // What narrows the search beyond the subject: folded, and its heading says how many are on.
            Section::make(__('scheduling::find.more.title'))
                ->description(fn () => ($n = $this->activeFilters()) > 0 ? trans_choice('scheduling::find.more.active', $n, ['n' => $n]) : __('scheduling::find.more.hint'))
                ->collapsed()->compact()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Select::make('language')->label(__('scheduling::find.fields.language'))
                            ->options(['ar' => __('scheduling::find.languages.ar'), 'en' => __('scheduling::find.languages.en')])
                            ->placeholder(__('scheduling::find.languages.any'))->native(false)->live()->afterStateUpdated($changed),
                        Select::make('age_group')->label(__('scheduling::find.fields.age_group'))->placeholder(__('scheduling::find.any_age'))->native(false)
                            ->options(fn () => AgeGroup::options())->live()->afterStateUpdated($changed),
                        Select::make('level_id')->label(__('scheduling::find.fields.level'))->placeholder(__('scheduling::find.any_level'))->native(false)
                            ->options(fn (Get $get) => $get('subject_id') ? app(CatalogApi::class)->levels($get('subject_id')) : [])
                            ->visible(fn (Get $get) => $get('subject_id') && app(CatalogApi::class)->levels($get('subject_id')) !== [])
                            ->live()->afterStateUpdated($changed),
                        Select::make('category_id')->label(__('scheduling::find.fields.category'))->placeholder(__('scheduling::find.any_category'))->native(false)
                            ->options(fn (Get $get) => $get('subject_id') ? app(CatalogApi::class)->categories($get('subject_id')) : [])
                            ->visible(fn (Get $get) => $get('subject_id') && app(CatalogApi::class)->categories($get('subject_id')) !== [])
                            ->live()->afterStateUpdated($changed),
                    ]),
                    ToggleButtons::make('gender')->label(__('scheduling::find.fields.gender'))
                        ->options(collect(TeacherSearch::GENDERS)->mapWithKeys(fn ($g) => [$g => __('scheduling::find.genders.'.$g)])->all())
                        ->inline()->required()->live()->afterStateUpdated($changed),
                    ToggleButtons::make('min_coverage')->label(__('scheduling::find.fields.min_coverage'))
                        ->options([8 => __('scheduling::find.coverage.all', ['n' => 8]), 7 => __('scheduling::find.coverage.allow_one', ['n' => 8])])
                        ->inline()->required()->visible($series)->live()->afterStateUpdated($changed),
                ]),
        ]);
    }

    /** How many of the filters that can leave a subject's teachers out are set (gender, language, level, category, age). */
    public function activeFilters(): int
    {
        return count(array_filter([
            ($this->data['gender'] ?? 'any') !== 'any',
            filled($this->data['language'] ?? null),
            filled($this->data['level_id'] ?? null),
            filled($this->data['category_id'] ?? null),
            filled($this->data['age_group'] ?? null),
        ]));
    }

    /** "Clear filters": the subject's teachers again, whoever and whatever they teach it in. */
    public function clearFilters(): void
    {
        $this->data = array_merge($this->data, ['gender' => 'any', 'language' => null, 'level_id' => null, 'category_id' => null, 'age_group' => null]);
        $this->resetResults();
    }

    /** "Allow one week missing" or another length, offered when nobody is free. */
    public function relax(string $what, int $value = 0): void
    {
        match ($what) {
            'coverage' => $this->data['min_coverage'] = 7,
            'duration' => $this->data['duration'] = in_array($value, [30, 45, 60], true) ? $value : $this->data['duration'],
            default => null,
        };
        $this->resetResults();
    }

    public function showTimes(string $tab): void
    {
        $this->timesTab = $tab === 'mine' ? 'mine' : 'free';
    }

    /**
     * "Available now" for this search: where its teachers are free across the
     * week and a few ready sets of weekly times (null until the subject is chosen).
     *
     * @return array{cells: array<int, array<int, array{count: int, names: list<string>}>>, suggestions: list<array<string, mixed>>, teachers: int}|null
     */
    #[Computed]
    public function freeTimes(): ?array
    {
        if (! $this->ready()) {
            return null;
        }

        return app(FreeTimesQuery::class)->run($this->teacherSearch([]));
    }

    /** A suggestion taken: its hours become the student's, the search runs, and its best teacher's times are picked. */
    public function useSuggestion(int $index): void
    {
        $suggestion = $this->freeTimes['suggestions'][$index] ?? null;
        if ($suggestion === null || $this->holdBatch !== null) {
            return;
        }

        $cells = [];
        $duration = (int) $this->data['duration'];
        foreach ($suggestion['times'] as ['weekday' => $weekday, 'time' => $time]) {
            [$h, $m] = array_map('intval', explode(':', $time));
            for ($hour = $h; $hour * 60 < $h * 60 + $m + $duration && $hour <= 23; $hour++) {
                $cells[$weekday][] = $hour;
            }
        }
        $this->cells = $cells;
        $this->search();

        $result = collect($this->results)->first(fn (array $r) => $r['teacher']->id === $suggestion['teacher_id']);
        $wanted = array_map(fn (array $t) => $t['weekday'].'|'.$t['time'], $suggestion['times']);
        $keys = $result ? array_values(array_map(fn (Candidate $c) => $c->key(), array_filter($result['candidates'],
            fn (Candidate $c) => in_array($c->studentWeekday.'|'.$c->studentTime, $wanted, true)))) : [];
        if (count($keys) === count($wanted)) {
            [$this->pickedTeacher, $this->picked] = [$suggestion['teacher_id'], $keys];
        }
    }

    /**
     * Why a search finds nobody, said where it shows: the subject has no
     * teacher taking students (or trials), the filters left everyone out,
     * nobody is free at all for this length and these weeks, or only the
     * marked hours are taken.
     */
    #[Computed]
    public function emptyReason(): ?string
    {
        if (! $this->ready()) {
            return null;
        }
        $teachers = DB::table('teacher_profiles as tp')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'tp.academy_id')->on('u.id', '=', 'tp.user_id'))
            ->join('teacher_subjects as ts', fn ($j) => $j->on('ts.academy_id', '=', 'tp.academy_id')->on('ts.teacher_id', '=', 'tp.user_id'))
            ->where('ts.subject_id', $this->data['subject_id'])
            ->where('tp.status', 'active')->where('u.status', 'active')
            ->where($this->isTrial() ? 'tp.accepts_trials' : 'tp.accepts_new', true);
        if (! $teachers->exists()) {
            return $this->isTrial() ? 'no_trial_teachers' : 'no_teachers';
        }
        if ($this->freeTimes['teachers'] > 0) {
            return 'times';
        }
        if ($this->activeFilters() > 0) {
            $open = $this->teacherSearch([], filters: false);
            if (app(FreeTimesQuery::class)->run($open)['teachers'] > 0) {
                return 'filters';
            }
        }

        return 'no_time';
    }

    /** The subject and the lesson's shape are there: a search can run. */
    public function ready(): bool
    {
        return filled($this->data['subject_id'] ?? null) && filled($this->data['start_date'] ?? null)
            && filled($this->data['duration'] ?? null) && filled($this->data['timezone'] ?? null);
    }

    /** Which step the bottom bar's one main button is for. */
    public function step(): string
    {
        return match (true) {
            $this->holdBatch !== null => 'confirm',
            $this->picked !== [] && $this->pickedTeacher !== null => 'hold',
            default => 'search',
        };
    }

    public function holdExpired(): bool
    {
        return $this->holdExpiresAt !== null && $this->holdExpiresAt <= CarbonImmutable::now()->getTimestamp();
    }

    /** The bar's summary: student · subject · 2 × 30 min · the times chosen. */
    public function summary(): array
    {
        $student = ($this->data['student_id'] ?? null) ? DB::table('users')->where('id', $this->data['student_id'])->value('name') : null;
        $subject = ($this->data['subject_id'] ?? null) ? DB::table('subjects')->where('id', $this->data['subject_id'])->value(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en') : null;
        $shape = $this->isTrial()
            ? __('scheduling::find.bar.trial', ['minutes' => (int) ($this->data['duration'] ?? 30)])
            : __('scheduling::find.bar.shape', ['n' => (int) ($this->data['per_week'] ?? 1), 'minutes' => (int) ($this->data['duration'] ?? 30)]);
        $picked = $this->pickedCandidates();
        $times = $picked !== []
            ? collect($this->inWeekOrder($picked))->map(fn (Candidate $c) => $this->dayName($c->studentWeekday).' '.Clock::wall($c->studentTime))->implode(' + ')
            : (($marked = array_sum(array_map('count', $this->cells))) > 0 ? trans_choice('scheduling::find.bar.marked', $marked, ['n' => $marked]) : null);

        return array_values(array_filter([$student, $subject, $shape, $times]));
    }

    public function toggleCell(int $weekday, int $hour): void
    {
        $hours = $this->cells[$weekday] ?? [];
        $this->cells[$weekday] = in_array($hour, $hours, true) ? array_values(array_diff($hours, [$hour])) : [...$hours, $hour];
        $this->resetResults();
    }

    public function clearCells(): void
    {
        $this->cells = [];
        $this->resetResults();
    }

    public function search(): void
    {
        $this->form->validate();
        if (array_filter($this->cells) === []) {
            Notification::make()->warning()->title(__('scheduling::find.errors.no_times'))->send();

            return;
        }
        $this->resetResults();
        $this->searched = true;
        $this->dispatch('ft-results');
    }

    public function pick(string $teacherId, string $key): void
    {
        if ($this->holdBatch !== null) {
            return;
        }
        if ($this->pickedTeacher !== $teacherId) {
            [$this->pickedTeacher, $this->picked] = [$teacherId, []];
        }

        if (in_array($key, $this->picked, true)) {
            $this->picked = array_values(array_diff($this->picked, [$key]));
        } elseif ($this->isTrial()) {
            $this->picked = [$key];
        } elseif (count($this->picked) < (int) $this->data['per_week']) {
            $this->picked[] = $key;
        } else {
            Notification::make()->warning()->title(__('scheduling::find.errors.enough', ['n' => $this->data['per_week']]))->send();
        }

        if ($this->picked === []) {
            $this->pickedTeacher = null;
        }
    }

    public function usePattern(string $teacherId): void
    {
        $result = collect($this->results)->first(fn (array $r) => $r['teacher']->id === $teacherId);
        if ($result && $result['pattern'] && $this->holdBatch === null) {
            $this->pickedTeacher = $teacherId;
            $this->picked = array_map(fn (Candidate $c) => $c->key(), $result['pattern']);
        }
    }

    public function hold(): void
    {
        $candidates = $this->pickedCandidates();
        if ($candidates === [] || $this->pickedTeacher === null) {
            $this->refusal = $this->pickedTeacher === null ? null : __('scheduling::find.errors.hold_gone');

            return;
        }

        $occurrences = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate->occurrences as $i => $ts) {
                if (! in_array(CarbonImmutable::createFromTimestamp($ts, 'UTC')->setTimezone($this->teacherTz())->toDateString(), $candidate->conflictDates, true)) {
                    $occurrences[] = $ts;
                }
            }
        }

        try {
            $this->holdBatch = app(PlaceBookingHold::class)->handle($this->pickedTeacher, $this->data['student_id'] ?: null, $occurrences, (int) $this->data['duration'], (string) auth()->id());
        } catch (ValidationException $e) {
            // Said beside the button too; ShowRefusals sends it as a notification.
            $this->refusal = $e->validator->errors()->first();
            unset($this->results);

            throw $e;
        }
        $this->refusal = null;
        $this->holdExpiresAt = CarbonImmutable::now()->addMinutes(BookingHold::MINUTES)->getTimestamp();
        Notification::make()->success()->title(__('scheduling::find.held', ['count' => count($occurrences)]))->send();
    }

    public function release(): void
    {
        BookingHold::query()->where('created_by', auth()->id())->delete();
        [$this->holdBatch, $this->holdExpiresAt, $this->picked, $this->pickedTeacher, $this->refusal] = [null, null, [], null, null];
        unset($this->results);
    }

    /** The hold ran out: the same times held again, if they are still free. */
    public function holdAgain(): void
    {
        [$teacher, $picked] = [$this->pickedTeacher, $this->picked];
        if ($this->holdBatch !== null) {
            BookingHold::query()->where('batch_id', $this->holdBatch)->where('created_by', auth()->id())->delete();
        }
        [$this->holdBatch, $this->holdExpiresAt, $this->pickedTeacher, $this->picked] = [null, null, $teacher, $picked];
        unset($this->results);
        $this->hold();
    }

    public function confirm(string $kind): void
    {
        if ($this->holdBatch === null) {
            return;
        }
        if (! $this->data['student_id']) {
            Notification::make()->warning()->title(__('scheduling::find.errors.student_needed'))->send();

            return;
        }
        // A trial is sales' to book too; a weekly series is the scheduler's (docs/architecture/01, permissions).
        if (! Gate::allows(($kind === 'series' ? Ability::ManageSessions : Ability::BookTrials)->value)) {
            Notification::make()->danger()->title(__('scheduling::find.errors.cannot_book'))->send();

            return;
        }

        if ($kind === 'series') {
            $this->mountAction('enroll');

            return;
        }

        try {
            $trial = app(ConfirmBooking::class)->trial($this->holdBatch, $this->data['student_id'], $this->data['subject_id'], (string) auth()->id());
        } catch (ValidationException $e) {
            $this->refusal = $e->validator->errors()->first();

            throw $e;
        }
        $this->bookingDone();

        Notification::make()->success()
            ->title(__('scheduling::find.booked.trial', ['date' => $this->studentDate($trial->starts_at)]))
            ->body(__('scheduling::find.booked.trial_told'))
            ->send();
        if ($this->back !== null) {
            $this->redirect($this->back, navigate: true);
        }
    }

    /** The enrolment step: plan, payer and price region, then one action does the rest. */
    public function enrollAction(): Action
    {
        return Action::make('enroll')
            ->modalHeading(__('scheduling::find.enroll.title'))
            ->modalDescription(fn () => __('scheduling::find.enroll.intro', [
                'per_week' => trans_choice('scheduling::find.per_week', count($this->picked), ['n' => count($this->picked)]),
                'minutes' => $this->data['duration'],
                'teacher' => collect($this->results)->first(fn (array $r) => $r['teacher']->id === $this->pickedTeacher)['teacher']->name ?? '',
            ]))
            ->modalSubmitActionLabel(__('scheduling::find.enroll.submit'))
            // Nothing to enrol on until a plan of this shape exists: the button waits, the form says why.
            // Filament hands the submit button in by the name \$action (by type it would be this action itself).
            ->modalSubmitAction(fn (Action $action) => $action->disabled(fn () => $this->matchingPlans() === []))
            ->modalWidth('lg')
            ->fillForm(fn () => [
                'price_region_id' => collect($this->regions())->first(fn (RegionOption $r) => $r->isDefault)?->id ?? ($this->regions()[0]->id ?? null),
                'payer_id' => array_key_first($this->payers()),
                'plan_id' => null,
            ])
            ->schema([
                Select::make('price_region_id')->label(__('scheduling::find.enroll.region'))
                    ->options(fn () => collect($this->regions())->mapWithKeys(fn (RegionOption $r) => [$r->id => $r->name.' · '.$r->currency])->all())
                    ->required()->native(false)->live(),
                Radio::make('plan_id')->label(__('scheduling::find.enroll.plan'))
                    ->options(fn (Get $get) => collect($this->matchingPlans())->mapWithKeys(fn (PlanOption $p) => [$p->id => __('scheduling::find.enroll.plan_option', [
                        'name' => $p->name,
                        'price' => isset($p->prices[$get('price_region_id')]) ? $p->prices[$get('price_region_id')]->format() : __('scheduling::find.enroll.no_price'),
                    ])])->all())
                    ->disableOptionWhen(fn (string $value, Get $get) => ! isset(collect($this->matchingPlans())->firstWhere('id', $value)?->prices[$get('price_region_id')]))
                    ->required()->live()
                    ->visible(fn () => $this->matchingPlans() !== []),
                Text::make(fn () => __('scheduling::find.enroll.no_plans', [
                    'minutes' => $this->data['duration'],
                    'per_week' => trans_choice('scheduling::find.per_week', count($this->picked), ['n' => count($this->picked)]),
                ]).(Gate::allows(Ability::ManageBilling->value) ? '' : ' '.__('scheduling::find.enroll.no_plans_ask')))->visible(fn () => $this->matchingPlans() === [])->color('danger'),
                Actions::make([$this->createPlanAction()])->key('newPlan')
                    ->visible(fn () => $this->matchingPlans() === [] && Gate::allows(Ability::ManageBilling->value)),
                Radio::make('payer_id')->label(__('scheduling::find.enroll.payer'))
                    ->options(fn () => $this->payers())->required()->live(),
                // A course joining the student's shared package draws on its balance: no invoice, so no price or coupon.
                Text::make(fn (Get $get) => $this->joining($get) ? __('scheduling::find.enroll.joins_package') : '')->color('info')
                    ->visible(fn (Get $get) => $this->joining($get)),
                TextInput::make('coupon')->label(__('scheduling::find.enroll.coupon'))->maxLength(32)->live(onBlur: true)
                    ->extraInputAttributes(['dir' => 'ltr', 'style' => 'text-transform: uppercase'])
                    ->visible(fn (Get $get) => $this->matchingPlans() !== [] && ! $this->joining($get)),
                Text::make(fn (Get $get) => $this->quote($get)?->couponError ?? '')->color('danger')
                    ->visible(fn (Get $get) => filled($this->quote($get)?->couponError)),
                Text::make(fn (Get $get) => $this->quote($get)?->couponNote ?? '')->color('warning')
                    ->visible(fn (Get $get) => filled($this->quote($get)?->couponNote)),
                Text::make(fn (Get $get) => $this->priceLine($this->quote($get)))
                    ->visible(fn (Get $get) => $this->quote($get) !== null && ! $this->joining($get)),
            ])
            ->action(function (array $data, Action $action) {
                if (blank($data['plan_id'] ?? null) || blank($data['payer_id'] ?? null) || blank($data['price_region_id'] ?? null)) {
                    Notification::make()->danger()->title(__('scheduling::find.errors.plan'))->send();
                    $action->halt();
                }
                try {
                    $booked = app(EnrollStudent::class)->handle(
                        $this->holdBatch,
                        $this->data['student_id'],
                        $this->data['subject_id'],
                        new EnrollmentTerms($data['plan_id'], $data['payer_id'], $data['price_region_id'], filled($data['coupon'] ?? null) ? $data['coupon'] : null),
                        (string) auth()->id(),
                    );
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
                    $action->halt();
                }
                $this->bookingDone();

                $date = $booked->firstSession ? $this->studentDate($booked->firstSession) : '—';
                Notification::make()->success()->persistent()
                    // A course that joined the student's shared package has no invoice of its own.
                    ->title($booked->invoice === null
                        ? __('scheduling::find.booked.joined', ['count' => $booked->sessions, 'date' => $date])
                        : __('scheduling::find.booked.series', [
                            'count' => $booked->sessions,
                            'date' => $date,
                            'number' => $booked->invoice->number,
                            'total' => $booked->invoice->total->format(),
                        ]))
                    ->body($booked->skippedDates !== [] ? __('scheduling::find.booked.skipped', ['dates' => implode('، ', $booked->skippedDates)]) : null)
                    ->actions(array_values(array_filter([
                        $booked->invoice === null || ! Gate::allows(Ability::ViewBilling->value) ? null : Action::make('invoice')->label(__('scheduling::find.enroll.open_invoice'))->button()
                            ->url(route('filament.academy.resources.invoices.view', ['record' => $booked->invoice->id])),
                        Action::make('account')->label(__('scheduling::find.enroll.open_account'))->link()
                            ->url(route('filament.academy.resources.enrollments.view', ['record' => $booked->enrollmentId])),
                    ])))
                    ->send();
            });
    }

    public function heldCount(): int
    {
        return $this->holdBatch && Str::isUuid($this->holdBatch) ? BookingHold::query()->where('batch_id', $this->holdBatch)->count() : 0;
    }

    /** Whether the chosen shared plan is one the student already has with this payer: the course joins it. */
    /**
     * The plan the enrolment is missing, made where it is missed: this lesson
     * length and lessons a week, a block of four weeks, a price per region
     * (through BillingApi, so SavePlan's checks and audit), then picked.
     */
    private function createPlanAction(): Action
    {
        $perWeek = fn () => max(1, count($this->picked));
        $minutes = fn () => (int) $this->data['duration'];

        return Action::make('createPlan')
            ->label(__('scheduling::find.enroll.create_plan'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('primary')
            ->modalHeading(__('scheduling::find.enroll.create_plan'))
            ->modalDescription(fn () => __('scheduling::find.enroll.create_plan_hint'))
            ->modalWidth('md')
            ->fillForm(fn () => [
                'name_ar' => __('scheduling::find.enroll.plan_name', ['minutes' => $minutes(), 'per_week' => trans_choice('scheduling::find.per_week', $perWeek(), ['n' => $perWeek()], 'ar')], 'ar'),
                'name_en' => __('scheduling::find.enroll.plan_name', ['minutes' => $minutes(), 'per_week' => trans_choice('scheduling::find.per_week', $perWeek(), ['n' => $perWeek()], 'en')], 'en'),
                'sessions_per_block' => $perWeek() * 4,
            ])
            ->schema(fn () => [
                TextInput::make('name_ar')->label(__('scheduling::find.enroll.plan_name_ar'))->required()->maxLength(120),
                TextInput::make('name_en')->label(__('scheduling::find.enroll.plan_name_en'))->required()->maxLength(120),
                TextInput::make('sessions_per_block')->label(__('scheduling::find.enroll.plan_block'))->numeric()->integer()->minValue(1)->maxValue(200)->required(),
                ...collect($this->regions())->map(fn (RegionOption $r) => TextInput::make('price_'.$r->id)
                    ->label(__('scheduling::find.enroll.plan_price', ['region' => $r->name, 'currency' => $r->currency]))
                    ->numeric()->minValue(0)->required($r->isDefault))->all(),
            ])
            ->action(function (array $data, Set $set) use ($perWeek, $minutes) {
                $prices = collect($this->regions())
                    ->mapWithKeys(fn (RegionOption $r) => [$r->id => (string) ($data['price_'.$r->id] ?? '')])
                    ->filter(fn (string $amount) => $amount !== '')->all();
                $id = app(BillingApi::class)->createPlan(new NewPlan(
                    trim($data['name_ar']), trim($data['name_en']), $minutes(), $perWeek(), (int) $data['sessions_per_block'], $prices,
                ));
                $set('plan_id', $id);
                Notification::make()->success()->title(__('scheduling::find.enroll.plan_created'))->send();
            });
    }

    private function joining(Get $get): bool
    {
        $plan = collect($this->matchingPlans())->firstWhere('id', $get('plan_id'));

        return $plan?->shared === true && filled($get('payer_id'))
            && app(BillingApi::class)->sharedPackage((string) $this->data['student_id'], $plan->id, (string) $get('payer_id')) !== null;
    }

    /** @var array<string, ?PriceQuote> quotes worked out in this request */
    private array $quotes = [];

    /** What the chosen plan comes to for this student and payer, with the family discount and the coupon typed. */
    private function quote(Get $get): ?PriceQuote
    {
        if (blank($get('plan_id')) || blank($get('payer_id')) || blank($get('price_region_id'))) {
            return null;
        }

        $key = implode('|', [$get('plan_id'), $get('price_region_id'), $get('payer_id'), $this->data['student_id'], $get('coupon')]);

        return $this->quotes[$key] ??= app(BillingApi::class)->quote($get('plan_id'), $get('price_region_id'), $get('payer_id'), $this->data['student_id'], $get('coupon'));
    }

    /** "400 ر.س. − خصم الإخوات (10%) 40 ر.س. = 360 ر.س." */
    private function priceLine(?PriceQuote $quote): string
    {
        if ($quote === null) {
            return '';
        }
        if ($quote->discounts === []) {
            return __('scheduling::find.enroll.price', ['total' => $quote->total->format()]);
        }

        return __('scheduling::find.enroll.price_after', [
            'price' => $quote->price->format(),
            'discounts' => implode(' · ', array_map(fn (array $d) => $d['label'].' −'.$d['amount']->format(), $quote->discounts)),
            'total' => $quote->total->format(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function results(): array
    {
        if (! $this->searched || array_filter($this->cells) === []) {
            return [];
        }

        return app(FindTeachersQuery::class)->run($this->teacherSearch($this->cells));
    }

    /**
     * The search as the form says, at these hours of the student's week
     * (FreeTimesQuery puts its own); without the filters for "clear filters".
     *
     * @param  array<int|string, list<int>>  $cells
     */
    private function teacherSearch(array $cells, bool $filters = true): TeacherSearch
    {
        // A trial is one lesson: any free time in the next two weeks will do.
        $trial = $this->isTrial();
        $preference = new StudentPreference(
            timezone: $this->data['timezone'],
            windows: StudentPreference::windowsFromHours($cells),
            perWeek: $trial ? 1 : (int) $this->data['per_week'],
            durationMin: (int) $this->data['duration'],
            startDate: CarbonImmutable::parse($this->data['start_date']),
            weeks: $trial ? 2 : (int) $this->data['weeks'],
            minCoverage: $trial ? 1 : (int) $this->data['min_coverage'],
        );

        return new TeacherSearch(
            subjectId: $this->data['subject_id'],
            preference: $preference,
            gender: $filters ? $this->data['gender'] : 'any',
            language: $filters ? (($this->data['language'] ?? null) ?: null) : null,
            forTrial: $trial,
            searcherId: (string) auth()->id(),
            levelId: $filters ? (($this->data['level_id'] ?? null) ?: null) : null,
            categoryId: $filters ? (($this->data['category_id'] ?? null) ?: null) : null,
            ageGroup: $filters ? (($this->data['age_group'] ?? null) ?: null) : null,
            forMinor: $this->forMinor(),
        );
    }

    /** The student is under 18 (their profile), or, with none chosen, the age group searched for is a child's. */
    private function forMinor(): bool
    {
        $studentId = ($this->data['student_id'] ?? null) ?: null;
        if ($studentId !== null) {
            return app(PeopleApi::class)->isMinor($studentId);
        }

        return in_array($this->data['age_group'] ?? null, [AgeGroup::Kids->value, AgeGroup::Middle->value, AgeGroup::Teen->value], true);
    }

    /** @return list<Candidate> */
    public function pickedCandidates(): array
    {
        $result = collect($this->results)->first(fn (array $r) => $r['teacher']->id === $this->pickedTeacher);

        return $result ? array_values(array_filter($result['candidates'], fn (Candidate $c) => in_array($c->key(), $this->picked, true))) : [];
    }

    /**
     * @template T
     *
     * @param  array<int, T>|list<Candidate>  $items  keyed by ISO weekday, or candidates
     * @return array<int, T>|list<Candidate> in this locale's week order (Saturday first in Arabic)
     */
    public function inWeekOrder(array $items): array
    {
        $order = array_flip($this->gridDays());

        if (array_is_list($items) && ($items[0] ?? null) instanceof Candidate) {
            usort($items, fn (Candidate $a, Candidate $b) => $order[$a->studentWeekday] <=> $order[$b->studentWeekday]);

            return $items;
        }
        uksort($items, fn (int $a, int $b) => $order[$a] <=> $order[$b]);

        return $items;
    }

    /** The first free day of a weekly time, on the student's clock (a trial takes that one). */
    public function firstFree(Candidate $candidate, string $teacherTz): ?string
    {
        foreach ($candidate->occurrences as $ts) {
            $at = CarbonImmutable::createFromTimestamp($ts, 'UTC');
            if (! in_array($at->setTimezone($teacherTz)->toDateString(), $candidate->conflictDates, true) && $at->isFuture()) {
                return $at->setTimezone($this->data['timezone'])->locale(app()->getLocale())->translatedFormat('j M');
            }
        }

        return null;
    }

    public function dayName(int $weekday): string
    {
        return __('scheduling::shifts.days.'.$weekday);
    }

    public function city(string $tz): string
    {
        return LiveBoardView::city($tz);
    }

    /** Grid columns: Saturday first in Arabic, Monday first otherwise. */
    public function gridDays(): array
    {
        return app()->getLocale() === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7];
    }

    /** @return list<PlanOption> active plans with this session length and weekly count */
    private function matchingPlans(): array
    {
        return array_values(array_filter(
            app(BillingApi::class)->planOptions((int) $this->data['duration']),
            // A shared package's lessons a week are across the student's courses, so only its length has to match.
            fn (PlanOption $p) => $p->sessionMinutes === (int) $this->data['duration'] && ($p->shared || $p->sessionsPerWeek === count($this->picked)),
        ));
    }

    /** @return list<RegionOption> */
    private function regions(): array
    {
        return app(BillingApi::class)->regions();
    }

    /** @return array<string, string> who can pay: the student's guardians (primary first), then the student */
    private function payers(): array
    {
        $studentId = $this->data['student_id'];
        $guardians = DB::table('guardian_student as g')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.guardian_id'))
            ->where('g.student_id', $studentId)->orderByDesc('g.is_primary')->orderBy('u.name')
            ->pluck('u.name', 'u.id')
            ->map(fn (string $name) => __('scheduling::find.enroll.payer_guardian', ['name' => $name]));
        $student = DB::table('users')->where('id', $studentId)->value('name');

        return $guardians->all() + [$studentId => __('scheduling::find.enroll.payer_student', ['name' => $student])];
    }

    private function bookingDone(): void
    {
        [$this->holdBatch, $this->holdExpiresAt, $this->picked, $this->pickedTeacher, $this->refusal] = [null, null, [], null, null];
        unset($this->results);
    }

    private function studentDate(CarbonImmutable $at): string
    {
        return $at->setTimezone($this->data['timezone'])->locale(app()->getLocale())->translatedFormat(Clock::pattern('l j F · H:i'));
    }

    private function studentChosen(?string $studentId): void
    {
        $tz = $studentId ? DB::table('users')->where('id', $studentId)->value('timezone') : null;
        if ($tz) {
            $this->data['timezone'] = $tz;
        }
        if ($studentId) {
            // Who should teach them, and their age, from their profile.
            $people = app(PeopleApi::class);
            $this->data['gender'] = $people->studentDetails($studentId)->teacherGender ?? 'any';
            $this->data['age_group'] = AgeGroup::forAge($people->studentProfile($studentId)->age)?->value;
        }
        $this->resetResults();
    }

    private function teacherTz(): string
    {
        return collect($this->results)->first(fn (array $r) => $r['teacher']->id === $this->pickedTeacher)['teacher']->timezone ?? 'UTC';
    }

    private function resetResults(): void
    {
        if ($this->holdBatch === null) {
            [$this->picked, $this->pickedTeacher] = [[], null];
        }
        $this->searched = false;
        $this->refusal = null;
        unset($this->results);
    }

    /** @return array<string, string> students of this academy, with their city */
    private static function students(): array
    {
        return DB::table('users as u')
            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', 'student')
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.timezone'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->timezone ? ' · '.LiveBoardView::city($s->timezone) : '')])
            ->all();
    }
}
