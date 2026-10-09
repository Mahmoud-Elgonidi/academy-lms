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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Billing\Contracts\DTOs\RegionOption;
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
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
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
        }
    }

    public function isTrial(): bool
    {
        return ($this->data['mode'] ?? 'series') === 'trial';
    }

    public function form(Schema $schema): Schema
    {
        $series = fn (Get $get) => $get('mode') !== 'trial';

        return $schema->statePath('data')->components([
            ToggleButtons::make('mode')->hiddenLabel()->inline()->required()->live()
                ->options(['series' => __('scheduling::find.modes.series'), 'trial' => __('scheduling::find.modes.trial')])
                ->icons(['series' => Heroicon::OutlinedCalendarDays, 'trial' => Heroicon::OutlinedSparkles])
                ->afterStateUpdated(fn () => $this->resetResults()),
            Grid::make(2)->schema([
                Select::make('student_id')->label(__('scheduling::find.fields.student'))
                    ->options(fn () => self::students())->searchable()->live()
                    ->afterStateUpdated(fn (?string $state) => $this->studentChosen($state)),
                Select::make('timezone')->label(__('scheduling::find.fields.timezone'))
                    ->options(fn () => array_combine(Timezones::all(), Timezones::all()))->searchable()->required()->live(),
                Select::make('subject_id')->label(__('scheduling::find.fields.subject'))
                    ->options(fn () => DB::table('subjects')->orderBy('name_ar')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id'))->required()->native(false),
                Select::make('language')->label(__('scheduling::find.fields.language'))
                    ->options(['ar' => __('scheduling::find.languages.ar'), 'en' => __('scheduling::find.languages.en')])
                    ->placeholder(__('scheduling::find.languages.any'))->native(false),
            ]),
            ToggleButtons::make('gender')->label(__('scheduling::find.fields.gender'))
                ->options(collect(TeacherSearch::GENDERS)->mapWithKeys(fn ($g) => [$g => __('scheduling::find.genders.'.$g)])->all())
                ->inline()->required(),
            Grid::make(3)->schema([
                Select::make('per_week')->label(__('scheduling::find.fields.per_week'))
                    ->options(array_combine(range(1, 5), array_map(fn ($n) => trans_choice('scheduling::find.per_week', $n, ['n' => $n]), range(1, 5))))->required()->native(false)
                    ->visible($series),
                ToggleButtons::make('duration')->label(__('scheduling::find.fields.duration'))
                    ->options([30 => '30', 45 => '45', 60 => '60'])->inline()->required(),
                DatePicker::make('start_date')->label(__('scheduling::find.fields.start_date'))->required()->native(false),
            ]),
            ToggleButtons::make('min_coverage')->label(__('scheduling::find.fields.min_coverage'))
                ->options([8 => __('scheduling::find.coverage.all', ['n' => 8]), 7 => __('scheduling::find.coverage.allow_one', ['n' => 8])])
                ->inline()->required()->visible($series),
        ]);
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

        $this->holdBatch = app(PlaceBookingHold::class)->handle($this->pickedTeacher, $this->data['student_id'] ?: null, $occurrences, (int) $this->data['duration'], (string) auth()->id());
        $this->holdExpiresAt = CarbonImmutable::now()->addMinutes(BookingHold::MINUTES)->getTimestamp();
        Notification::make()->success()->title(__('scheduling::find.held', ['count' => count($occurrences)]))->send();
    }

    public function release(): void
    {
        BookingHold::query()->where('created_by', auth()->id())->delete();
        [$this->holdBatch, $this->holdExpiresAt, $this->picked, $this->pickedTeacher] = [null, null, [], null];
        unset($this->results);
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

        $trial = app(ConfirmBooking::class)->trial($this->holdBatch, $this->data['student_id'], $this->data['subject_id'], (string) auth()->id());
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
                ]))->visible(fn () => $this->matchingPlans() === [])->color('danger'),
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
                        $booked->invoice === null ? null : Action::make('invoice')->label(__('scheduling::find.enroll.open_invoice'))->button()
                            ->url(route('filament.academy.resources.invoices.view', ['record' => $booked->invoice->id])),
                        Action::make('account')->label(__('scheduling::find.enroll.open_account'))->link()
                            ->url(route('filament.academy.resources.enrollments.view', ['record' => $booked->enrollmentId])),
                    ])))
                    ->send();
            });
    }

    public function heldCount(): int
    {
        return $this->holdBatch ? BookingHold::query()->where('batch_id', $this->holdBatch)->count() : 0;
    }

    /** Whether the chosen shared plan is one the student already has with this payer: the course joins it. */
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

        // A trial is one lesson: any free time in the next two weeks will do.
        $trial = $this->isTrial();
        $preference = new StudentPreference(
            timezone: $this->data['timezone'],
            windows: StudentPreference::windowsFromHours($this->cells),
            perWeek: $trial ? 1 : (int) $this->data['per_week'],
            durationMin: (int) $this->data['duration'],
            startDate: CarbonImmutable::parse($this->data['start_date']),
            weeks: $trial ? 2 : (int) $this->data['weeks'],
            minCoverage: $trial ? 1 : (int) $this->data['min_coverage'],
        );

        return app(FindTeachersQuery::class)->run(new TeacherSearch(
            subjectId: $this->data['subject_id'],
            preference: $preference,
            gender: $this->data['gender'],
            language: $this->data['language'] ?: null,
            forTrial: $trial,
            searcherId: (string) auth()->id(),
        ));
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
        [$this->holdBatch, $this->holdExpiresAt, $this->picked, $this->pickedTeacher] = [null, null, [], null];
        unset($this->results);
    }

    private function studentDate(CarbonImmutable $at): string
    {
        return $at->setTimezone($this->data['timezone'])->locale(app()->getLocale())->translatedFormat('l j F · H:i');
    }

    private function studentChosen(?string $studentId): void
    {
        $tz = $studentId ? DB::table('users')->where('id', $studentId)->value('timezone') : null;
        if ($tz) {
            $this->data['timezone'] = $tz;
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
