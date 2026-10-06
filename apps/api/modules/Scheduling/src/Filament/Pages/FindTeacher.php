<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\PlaceBookingHold;
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
 * a 10-minute hold, then a trial or the weekly series.
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
        $this->form->fill([
            'student_id' => request()->query('student'),
            'timezone' => app(TenantContext::class)->require()->defaultTimezone,
            'subject_id' => request()->query('subject'),
            'gender' => 'any',
            'language' => null,
            'per_week' => 2,
            'duration' => 30,
            'start_date' => CarbonImmutable::now()->addDay()->toDateString(),
            'weeks' => 8,
            'min_coverage' => 8,
        ]);
        if ($this->data['student_id']) {
            $this->studentChosen($this->data['student_id']);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
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
                    ->options(array_combine(range(1, 5), array_map(fn ($n) => trans_choice('scheduling::find.per_week', $n, ['n' => $n]), range(1, 5))))->required()->native(false),
                ToggleButtons::make('duration')->label(__('scheduling::find.fields.duration'))
                    ->options([30 => '30', 45 => '45', 60 => '60'])->inline()->required(),
                DatePicker::make('start_date')->label(__('scheduling::find.fields.start_date'))->required()->native(false),
            ]),
            ToggleButtons::make('min_coverage')->label(__('scheduling::find.fields.min_coverage'))
                ->options([8 => __('scheduling::find.coverage.all', ['n' => 8]), 7 => __('scheduling::find.coverage.allow_one', ['n' => 8])])
                ->inline()->required(),
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
        if (! Gate::allows(Ability::ManageSessions->value)) {
            Notification::make()->danger()->title(__('scheduling::find.errors.cannot_book'))->send();

            return;
        }

        $sessions = app(ConfirmBooking::class)->handle($this->holdBatch, $this->data['student_id'], $this->data['subject_id'], $kind, (string) auth()->id());
        [$this->holdBatch, $this->holdExpiresAt, $this->picked, $this->pickedTeacher] = [null, null, [], null];
        unset($this->results);

        Notification::make()->success()
            ->title(__('scheduling::find.booked.'.$kind, ['count' => count($sessions), 'date' => $sessions[0]->starts_at->setTimezone($this->data['timezone'])->locale(app()->getLocale())->translatedFormat('l j F · H:i')]))
            ->send();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function results(): array
    {
        if (! $this->searched || array_filter($this->cells) === []) {
            return [];
        }

        $preference = new StudentPreference(
            timezone: $this->data['timezone'],
            windows: StudentPreference::windowsFromHours($this->cells),
            perWeek: (int) $this->data['per_week'],
            durationMin: (int) $this->data['duration'],
            startDate: CarbonImmutable::parse($this->data['start_date']),
            weeks: (int) $this->data['weeks'],
            minCoverage: (int) $this->data['min_coverage'],
        );

        return app(FindTeachersQuery::class)->run(new TeacherSearch(
            subjectId: $this->data['subject_id'],
            preference: $preference,
            gender: $this->data['gender'],
            language: $this->data['language'] ?: null,
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
