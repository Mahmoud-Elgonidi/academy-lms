<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Scheduling\Actions\ExportLessons;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Filament\Calendar\CalendarView;
use Modules\Scheduling\Filament\Calendar\Lanes;
use Modules\Scheduling\Filament\LiveBoardView;
use Modules\Scheduling\Filament\Resources\Enrollments\EnrollmentResource;
use Modules\Scheduling\Live\BoardChanged;
use Modules\Scheduling\Queries\Calendar\CalendarFilter;
use Modules\Scheduling\Queries\Calendar\CalendarRange;
use Modules\Scheduling\Queries\Calendar\LessonsCalendar;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The academy's calendar (docs/architecture/01, sections 7 and 17): every
 * lesson by day, week or month on the viewer's clock, narrowed to a teacher
 * (with their shifts and time off behind), a student, a subject, a kind or
 * statuses; as a calendar, a list, or counted by status per teacher and
 * subject. Follows lessons live, and the range goes out as a spreadsheet.
 */
final class AcademyCalendar extends Page
{
    public const DISPLAYS = ['calendar', 'list', 'summary'];

    /** Teachers drawn as rows on a day before the rows stop showing their shifts (one read each). */
    private const SHIFT_ROWS = 80;

    protected string $view = 'scheduling::filament.calendar.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = -9;

    protected static ?string $slug = 'calendar';

    #[Url]
    public string $unit = 'week';

    #[Url]
    public string $display = 'calendar';

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $teacher = null;

    #[Url]
    public ?string $student = null;

    #[Url]
    public ?string $subject = null;

    #[Url]
    public ?string $type = null;

    /** @var list<string> */
    #[Url(as: 'status')]
    public array $statuses = [];

    #[Url(as: 'tz')]
    public ?string $zone = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewLiveBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::calendar.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::calendar.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::calendar.subtitle', ['zone' => Timezones::city($this->tz())]);
    }

    public function mount(): void
    {
        $this->unit = in_array($this->unit, CalendarRange::UNITS, true) ? $this->unit : 'week';
        $this->display = in_array($this->display, self::DISPLAYS, true) ? $this->display : 'calendar';
        $this->zone = Timezones::isValid($this->zone) ? $this->zone : app(ViewerTimezone::class)->effective();
        $this->filtersForm->fill(['teacher' => $this->teacher, 'student' => $this->student, 'subject' => $this->subject, 'type' => $this->type]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        $l = 'scheduling::calendar.filters.';
        $calendar = app(LessonsCalendar::class);

        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                Select::make('teacher')->label(__($l.'teacher'))->hiddenLabel()->placeholder(__($l.'teacher'))->searchable()->native(false)->live()
                    ->options(fn () => $calendar->teachers()),
                Select::make('student')->label(__($l.'student'))->hiddenLabel()->placeholder(__($l.'student'))->searchable()->native(false)->live()
                    ->getSearchResultsUsing(fn (string $search) => $calendar->students($search))
                    ->getOptionLabelUsing(fn ($value) => $calendar->name(is_string($value) ? $value : null)),
                Select::make('subject')->label(__($l.'subject'))->hiddenLabel()->placeholder(__($l.'subject'))->native(false)->live()
                    ->options(fn () => $calendar->subjects()),
                Select::make('type')->label(__($l.'type'))->hiddenLabel()->placeholder(__($l.'type'))->native(false)->live()
                    ->options(collect(SessionType::cases())->mapWithKeys(fn (SessionType $t) => [$t->value => __('scheduling::calendar.types.'.$t->value)])->all()),
            ]),
        ]);
    }

    public function tz(): string
    {
        return Timezones::isValid($this->zone) ? (string) $this->zone : app(ViewerTimezone::class)->effective();
    }

    #[Computed]
    public function range(): CalendarRange
    {
        return CalendarRange::make($this->unit, $this->date, $this->tz(), CarbonImmutable::now());
    }

    public function filter(): CalendarFilter
    {
        return CalendarFilter::of($this->teacher, $this->student, $this->subject, $this->type, $this->statuses);
    }

    // ---- Moving around. ----

    public function go(int $by): void
    {
        $this->date = $this->range->step($by > 0 ? 1 : -1);
        unset($this->range);
    }

    public function today(): void
    {
        $this->date = null;
        unset($this->range);
    }

    public function setUnit(string $unit): void
    {
        if (in_array($unit, CalendarRange::UNITS, true)) {
            // Keep the day in view: today when it is in the range, else its first day.
            $range = $this->range;
            $today = CarbonImmutable::parse(CarbonImmutable::now()->setTimezone($range->timezone)->toDateString(), 'UTC');
            $this->date = $range->contains($today) ? null : $range->first->toDateString();
            $this->unit = $unit;
            unset($this->range);
        }
    }

    public function setDisplay(string $display): void
    {
        $this->display = in_array($display, self::DISPLAYS, true) ? $display : 'calendar';
    }

    /** A day of the week or month, as a calendar. */
    public function openDay(string $date): void
    {
        if (CalendarRange::day($date) !== null) {
            [$this->unit, $this->display, $this->date] = ['day', 'calendar', $date];
            unset($this->range);
        }
    }

    public function toggleStatus(string $status): void
    {
        if (in_array($status, LessonsCalendar::statuses(), true)) {
            $this->statuses = in_array($status, $this->statuses, true)
                ? array_values(array_diff($this->statuses, [$status]))
                : array_values(array_intersect(LessonsCalendar::statuses(), [...$this->statuses, $status]));
        }
    }

    public function clearStatuses(): void
    {
        $this->statuses = [];
    }

    public function clearFilters(): void
    {
        [$this->teacher, $this->student, $this->subject, $this->type, $this->statuses] = [null, null, null, null, []];
        $this->filtersForm->fill(['teacher' => null, 'student' => null, 'subject' => null, 'type' => null]);
    }

    /** From the summary: one teacher's (or subject's) lessons as a calendar. */
    public function focus(string $what, string $id): void
    {
        if ($what === 'teacher' || $what === 'subject') {
            $this->{$what} = $id;
            $this->display = 'calendar';
            $this->filtersForm->fill(['teacher' => $this->teacher, 'student' => $this->student, 'subject' => $this->subject, 'type' => $this->type]);
        }
    }

    public function setZone(string $timezone): void
    {
        if (Timezones::isValid($timezone)) {
            $this->zone = $timezone;
            unset($this->range);
        }
    }

    // ---- What the screen shows. ----

    /** The range's lessons by status (whatever statuses are picked), and the totals. @return array<string, mixed> */
    #[Computed]
    public function counts(): array
    {
        [$from, $to] = $this->range->bounds();
        $byStatus = app(LessonsCalendar::class)->byStatus($this->filter()->anyStatus(), $from, $to);
        $held = array_filter($byStatus, fn (string $s) => $s !== 'cancelled', ARRAY_FILTER_USE_KEY);

        return [
            'statuses' => array_map(fn (string $s, array $c) => $c + ['status' => $s, 'label' => __('scheduling::calendar.statuses.'.$s), 'on' => in_array($s, $this->statuses, true)],
                array_keys($byStatus), $byStatus),
            'lessons' => array_sum(array_column($held, 'count')),
            'hours' => $this->hours(array_sum(array_column($held, 'minutes'))),
            'cancelled' => $byStatus['cancelled']['count'],
        ];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function board(): array
    {
        $range = $this->range;
        $now = CarbonImmutable::now();
        $view = new CalendarView($range, $now);
        $base = [
            'label' => $this->rangeLabel($range),
            'serverNow' => $now->getTimestampMs(),
            // Midnight on the viewer's clock moves "today" and the now line to another day.
            'nextChangeAt' => $range->instant(CarbonImmutable::parse($now->setTimezone($range->timezone)->toDateString(), 'UTC')->addDay())->getTimestampMs(),
            'channel' => BoardChanged::channel((string) app(TenantContext::class)->id()),
            'zones' => $this->zones(),
        ];

        return $base + match (true) {
            $this->display === 'summary' => $this->summary($range),
            $this->display === 'list' => $this->list($range, $view),
            $range->unit === 'month' => $this->month($range, $view),
            $range->unit === 'day' => $this->day($range, $view),
            default => $this->week($range, $view),
        };
    }

    /** @return array<string, mixed> */
    private function week(CalendarRange $range, CalendarView $view): array
    {
        $calendar = app(LessonsCalendar::class);
        [$from, $to] = $range->bounds();
        $lessons = array_map($view->lesson(...), $calendar->lessons($this->filter(), $from, $to));
        $holidays = array_map(fn (array $h) => [$h['from'], $h['to'], $h['name']], $calendar->holidays($from, $to));
        $teacher = $this->filter()->teacherId;
        $working = $teacher !== null ? $calendar->working([$teacher], $from, $to)[$teacher] : null;

        $days = [];
        $spans = [];
        foreach ($range->days() as $day) {
            $date = $day->toDateString();
            $ofDay = array_values(array_filter($lessons, fn (array $l) => $l['date'] === $date));
            $shifts = $working ? $view->onDay($working['shifts'], $day) : [];
            $spans = [...$spans, ...$ofDay, ...$shifts];
            $days[] = [
                'date' => $date,
                'name' => $view->dayName($day, 'l'),
                'short' => $view->dayName($day, 'D'),
                'num' => $view->dayName($day, 'j M'),
                'full' => $view->dayName($day),
                'today' => $view->isToday($day),
                'count' => count($ofDay),
                'lessons' => $ofDay,
                'placed' => Lanes::place($ofDay),
                'shades' => [
                    ...array_map(fn (array $s) => $s + ['kind' => 'shift'], $shifts),
                    ...array_map(fn (array $s) => $s + ['kind' => 'off'], $working ? $view->onDay(array_map(fn (array $o) => [$o[0], $o[1], __('scheduling::calendar.off', ['reason' => __('scheduling::timeoff.reasons.'.$o[2])])], $working['off']), $day) : []),
                    ...array_map(fn (array $s) => $s + ['kind' => 'holiday'], $view->onDay($holidays, $day)),
                ],
            ];
        }

        return ['kind' => 'week', 'days' => $days, 'axis' => CalendarView::axis($spans), 'nowMinute' => $view->nowMinute(), 'shifts' => $working !== null];
    }

    /** @return array<string, mixed> */
    private function day(CalendarRange $range, CalendarView $view): array
    {
        $calendar = app(LessonsCalendar::class);
        [$from, $to] = $range->bounds();
        $day = $range->first;
        $filter = $this->filter();
        $lessons = array_map($view->lesson(...), $calendar->lessons($filter, $from, $to));

        // A row per teacher with lessons; with nothing narrowed, also those on shift with none (who is free today).
        $teachers = [];
        foreach ($lessons as $l) {
            $teachers[$l['teacherId']] ??= $l['teacher'];
        }
        if ($filter->teacherId !== null) {
            $teachers[$filter->teacherId] ??= (string) $calendar->name($filter->teacherId);
        } elseif ($filter->isEmpty()) {
            $teachers += $calendar->teachers();
        }
        $working = count($teachers) <= self::SHIFT_ROWS ? $calendar->working(array_map('strval', array_keys($teachers)), $from, $to) : [];
        $holidays = $view->onDay(array_map(fn (array $h) => [$h['from'], $h['to'], $h['name']], $calendar->holidays($from, $to)), $day);

        $rows = [];
        $spans = $lessons;
        foreach ($teachers as $id => $name) {
            $id = (string) $id;
            $ofTeacher = array_values(array_filter($lessons, fn (array $l) => $l['teacherId'] === $id));
            $shifts = isset($working[$id]) ? $view->onDay($working[$id]['shifts'], $day) : [];
            $off = isset($working[$id]) ? $view->onDay(array_map(fn (array $o) => [$o[0], $o[1], __('scheduling::calendar.off', ['reason' => __('scheduling::timeoff.reasons.'.$o[2])])], $working[$id]['off']), $day) : [];
            if ($ofTeacher === [] && $shifts === [] && $off === [] && $id !== $filter->teacherId) {
                continue;   // not working today
            }
            $spans = [...$spans, ...$shifts];
            $rows[] = [
                'id' => $id, 'name' => (string) $name, 'count' => count($ofTeacher), 'placed' => Lanes::place($ofTeacher, 2),
                'free' => $ofTeacher === [] && $shifts !== [],
                'shades' => [
                    ...array_map(fn (array $s) => $s + ['kind' => 'shift'], $shifts),
                    ...array_map(fn (array $s) => $s + ['kind' => 'off'], $off),
                    ...array_map(fn (array $s) => $s + ['kind' => 'holiday'], $holidays),
                ],
            ];
        }
        usort($rows, fn (array $a, array $b) => [$b['count'] > 0, $a['name']] <=> [$a['count'] > 0, $b['name']]);

        return [
            'kind' => 'day', 'rows' => $rows, 'axis' => CalendarView::axis($spans), 'nowMinute' => $view->nowMinute(), 'today' => $view->isToday($day),
            'holidays' => array_values(array_unique(array_filter(array_column($holidays, 'label')))),
            'days' => [['date' => $day->toDateString(), 'full' => $view->dayName($day), 'today' => $view->isToday($day), 'lessons' => $lessons, 'count' => count($lessons)]],
        ];
    }

    /** @return array<string, mixed> */
    private function month(CalendarRange $range, CalendarView $view): array
    {
        [$from, $to] = $range->bounds();
        $byDay = app(LessonsCalendar::class)->byDay($this->filter(), $from, $to, $range->timezone);
        $order = LessonsCalendar::statuses();
        $weeks = array_map(fn (array $week) => array_map(function (CarbonImmutable $day) use ($range, $byDay, $view, $order) {
            $counts = $range->contains($day) ? ($byDay[$day->toDateString()] ?? []) : [];
            uksort($counts, fn (string $a, string $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

            return [
                'date' => $day->toDateString(), 'num' => $day->day, 'in' => $range->contains($day), 'today' => $view->isToday($day),
                'full' => $view->dayName($day), 'total' => array_sum($counts), 'counts' => $counts,
            ];
        }, $week), $range->weeks());

        return ['kind' => 'month', 'weeks' => $weeks, 'heads' => array_map(fn (CarbonImmutable $d) => ['short' => $view->dayName($d, 'D'), 'name' => $view->dayName($d, 'l')], $range->weeks()[0])];
    }

    /** @return array<string, mixed> */
    private function list(CalendarRange $range, CalendarView $view): array
    {
        [$from, $to] = $range->bounds();
        $rows = app(LessonsCalendar::class)->lessons($this->filter(), $from, $to, LessonsCalendar::LIST_LIMIT + 1);
        $more = count($rows) > LessonsCalendar::LIST_LIMIT;
        $days = [];
        foreach (array_slice($rows, 0, LessonsCalendar::LIST_LIMIT) as $row) {
            $lesson = $view->lesson($row);
            $days[$lesson['date']] ??= ['date' => $lesson['date'], 'full' => $view->dayName(CarbonImmutable::parse($lesson['date'], 'UTC')),
                'today' => $view->isToday(CarbonImmutable::parse($lesson['date'], 'UTC')), 'lessons' => [], 'count' => 0];
            $days[$lesson['date']]['lessons'][] = $lesson;
            $days[$lesson['date']]['count']++;
        }

        return ['kind' => 'list', 'days' => array_values($days), 'more' => $more, 'limit' => LessonsCalendar::LIST_LIMIT];
    }

    /** @return array<string, mixed> */
    private function summary(CalendarRange $range): array
    {
        [$from, $to] = $range->bounds();
        $calendar = app(LessonsCalendar::class);
        $shape = fn (array $rows) => array_map(fn (array $r) => $r + ['hours' => $this->hours($r['minutes'])], $rows);
        $teachers = $calendar->byTeacher($this->filter(), $from, $to);
        $total = ['statuses' => array_fill_keys(LessonsCalendar::statuses(), 0), 'total' => 0, 'minutes' => 0];
        foreach ($teachers as $t) {
            foreach ($t['statuses'] as $s => $n) {
                $total['statuses'][$s] += $n;
            }
            $total['total'] += $t['total'];
            $total['minutes'] += $t['minutes'];
        }

        return [
            'kind' => 'summary',
            'columns' => array_map(fn (string $s) => ['status' => $s, 'label' => __('scheduling::calendar.statuses.'.$s)], LessonsCalendar::statuses()),
            'teachers' => $shape($teachers),
            'subjects' => $shape($calendar->bySubject($this->filter(), $from, $to)),
            'total' => $total + ['hours' => $this->hours($total['minutes'])],
        ];
    }

    // ---- Details and the spreadsheet. ----

    public function lessonAction(): Action
    {
        return Action::make('lesson')
            ->authorize(fn () => Gate::allows(Ability::ViewLiveBoard->value))
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalHeading(fn (array $arguments) => $this->details($arguments['id'] ?? null)['heading'] ?? __('scheduling::calendar.details.missing'))
            ->modalContent(fn (array $arguments) => view('scheduling::filament.calendar.lesson', ['lesson' => $this->details($arguments['id'] ?? null)]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('scheduling::calendar.details.close'));
    }

    /** @return array<string, mixed>|null */
    public function details(mixed $id): ?array
    {
        $row = is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id) ? app(LessonsCalendar::class)->lesson($id) : null;
        if ($row === null) {
            return null;
        }
        $range = $this->range;
        $now = CarbonImmutable::now();
        $lesson = (new CalendarView($range, $now))->lesson($row);
        $start = $row->starts_at->setTimezone($range->timezone);
        $l = 'scheduling::calendar.';
        // Their own clock, when it reads differently from the viewer's.
        $elsewhere = fn (?string $tz) => $tz !== null && Timezones::isValid($tz) && $row->starts_at->setTimezone($tz)->format('Y-m-d H:i') !== $start->format('Y-m-d H:i')
            ? trim(__($l.'details.their_time', ['time' => $row->starts_at->setTimezone($tz)->format('H:i'), 'city' => Timezones::city($tz),
                'day' => $row->starts_at->setTimezone($tz)->toDateString() !== $start->toDateString() ? $row->starts_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l') : '']))
            : null;
        $cancelled = $row->status === 'cancelled' && $row->reason !== null;
        $params = ['unit' => $this->unit, 'date' => $start->toDateString(), 'tz' => $range->timezone];

        return $lesson + [
            'heading' => $lesson['subject'].' · '.$lesson['from'],
            'when' => $start->locale(app()->getLocale())->translatedFormat('l j F Y'),
            'teacherTime' => $elsewhere($row->teacher_tz),
            'studentTime' => $elsewhere($row->student_tz),
            'originalTeacher' => $row->original_teacher,
            'funding' => __($l.'funding.'.$row->funding_status),
            'reason' => $cancelled ? __($l.'details.cancelled_reason', [
                'reason' => Lang::has($l.'reasons.'.$row->reason) ? __($l.'reasons.'.$row->reason) : $row->reason,
                'by' => Lang::has($l.'by.'.$row->reason_by) ? __($l.'by.'.$row->reason_by) : (string) $row->reason_by,
            ]) : null,
            'note' => $cancelled ? $row->reason_note : null,
            'review' => $row->status === 'needs_review' && $row->review_reason ? __('scheduling::board.review_reasons.'.$row->review_reason) : null,
            'dispute' => $row->dispute_id === null ? null : [
                'label' => __('scheduling::disputes.sides.'.$row->dispute_side).' · '.__('scheduling::disputes.statuses.'.$row->dispute_status),
                'url' => LessonDisputes::getUrl(['dispute' => $row->dispute_id]),
            ],
            'accountUrl' => $row->enrollment_id && EnrollmentResource::canAccess() ? EnrollmentResource::getUrl('view', ['record' => $row->enrollment_id]) : null,
            'boardUrl' => $row->starts_at->isSameDay($now) && in_array($row->status, ['scheduled', 'in_progress'], true) ? LiveBoard::getUrl() : null,
            'teacherUrl' => self::getUrl($params + ['teacher' => $row->teacher_id]),
            'studentUrl' => $row->student_id ? self::getUrl($params + ['student' => $row->student_id]) : null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label(__('scheduling::calendar.export.action'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(function () {
                    $export = app(ExportLessons::class);

                    return response()->download($export->handle($this->range, $this->filter()), $export->filename($this->range))->deleteFileAfterSend();
                }),
        ];
    }

    // ---- Words. ----

    public function rangeLabel(CalendarRange $range): string
    {
        $locale = app()->getLocale();
        $f = fn (CarbonImmutable $d, string $format) => $d->locale($locale)->translatedFormat($format);

        return match ($range->unit) {
            'day' => $f($range->first, 'l j F Y'),
            'month' => $f($range->first, 'F Y'),
            default => $range->first->month === $range->last->month
                ? $f($range->first, 'j').' – '.$f($range->last, 'j F Y')
                : $f($range->first, 'j F').' – '.$f($range->last, 'j F Y'),
        };
    }

    public function hours(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? (string) $h : $h.':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }

    /** @return list<array{id: string, label: string}> */
    private function zones(): array
    {
        $viewer = app(ViewerTimezone::class);
        $zones = array_unique(array_filter([$viewer->profile(), $viewer->academy(), 'Asia/Riyadh', 'Africa/Cairo', 'Asia/Dubai', 'Europe/London', 'America/New_York', $this->tz()]));

        return array_values(array_map(fn (string $tz) => [
            'id' => $tz,
            'label' => LiveBoardView::city($tz).($tz === $viewer->profile() ? ' · '.__('scheduling::board.zone_mine') : ''),
        ], $zones));
    }
}
