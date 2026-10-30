<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\Dst\DecideDstChange;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Actions\ShiftSlotTimes;
use Modules\Scheduling\Actions\ShiftTeacherLessons;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Filament\TimeOffForm;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * The DST planner's screen (docs/architecture/01, "مخطِّط التوقيت الصيفي"):
 * the coming clock changes, each with the lessons whose hour moves for the
 * student, what the family sees before and after, until when, whether they
 * were told, and whether the teacher is free to keep the student's hour
 * instead. One at a time or several at once. Moving a teacher's lessons for
 * a period (bulk rescheduling) starts here too.
 */
final class DstChanges extends Page
{
    protected string $view = 'scheduling::filament.dst.changes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = -4;

    protected static ?string $slug = 'clock-changes';

    /** @var list<string> notices picked for a decision on several at once */
    public array $selected = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageSessions->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::dst.nav');
    }

    /** Changes nobody decided yet whose family is told within two weeks. */
    public static function getNavigationBadge(): ?string
    {
        $now = CarbonImmutable::now();
        $count = ShiftNotice::query()->where('decision', 'pending')->where('changes_at', '>', $now)
            ->where('changes_at', '<=', $now->addDays(SendDstNotices::EARLY_DAYS + 7))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::dst.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::dst.subtitle', ['days' => PlanDstChanges::DAYS_AHEAD]).' '.ZoneHint::yours();
    }

    /**
     * The coming changes, one group per clock change (the zone and the night it changes).
     *
     * @return list<array{title: string, when: string, rows: list<array<string, mixed>>}>
     */
    #[Computed]
    public function changes(): array
    {
        $now = CarbonImmutable::now();
        $viewer = app(ViewerTimezone::class)->effective();
        $l = 'scheduling::dst.';
        $notices = ShiftNotice::query()
            ->join('users as s', fn ($j) => $j->on('s.academy_id', '=', 'schedule_shift_notices.academy_id')->on('s.id', '=', 'schedule_shift_notices.student_id'))
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 'schedule_shift_notices.academy_id')->on('t.id', '=', 'schedule_shift_notices.teacher_id'))
            ->where('schedule_shift_notices.changes_at', '>', $now)->orderBy('schedule_shift_notices.changes_at')
            ->get(['schedule_shift_notices.*', 's.name as student_name', 't.name as teacher_name']);
        $slots = RecurringSlot::query()->whereIn('id', $notices->pluck('slot_id'))->where('status', 'active')->get()->keyBy('id');
        $shift = app(ShiftSlotTimes::class);

        $groups = [];
        foreach ($notices as $n) {
            /** @var ShiftNotice $n */
            $at = self::clockChange($n->cause_tz, $n->changes_at);
            $key = $n->cause_tz.'@'.$at->getTimestamp();
            $groups[$key] ??= [
                'title' => __($l.'cause', ['city' => Timezones::city($n->cause_tz)]),
                'when' => $at->setTimezone($n->cause_tz)->locale(app()->getLocale())->translatedFormat('l j F'),
                'at' => $at->getTimestamp(),
                'rows' => [],
            ];

            $kept = $n->decision === 'keep_student';
            $problems = ! $kept && isset($slots[$n->slot_id])
                ? count($shift->plan([$slots[$n->slot_id]], $n->changes_at, $n->reverts_at, -$n->delta_min)['problems'])
                : null;
            $teacherAt = $n->changes_at->setTimezone($n->anchor_tz);
            $tellOn = $n->changes_at->subDays(SendDstNotices::EARLY_DAYS)->max($now);

            $groups[$key]['rows'][] = [
                'id' => $n->id,
                'student' => $n->getAttribute('student_name'),
                'teacher' => $n->getAttribute('teacher_name'),
                'studentZone' => Timezones::city($n->other_tz),
                'teacherZone' => Timezones::city($n->anchor_tz),
                'before' => self::weekdayTime($n->before_weekday, $n->before_time),
                'after' => self::weekdayTime($n->after_weekday, $n->after_time),
                'teacherBefore' => $teacherAt->locale(app()->getLocale())->translatedFormat(Clock::pattern('l H:i')),
                'teacherAfter' => $kept ? $teacherAt->subMinutes($n->delta_min)->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i')) : null,
                'kept' => $kept,
                'from' => __($l.'from', ['date' => $n->changes_at->setTimezone($viewer)->locale(app()->getLocale())->translatedFormat('j F')]),
                'until' => $n->reverts_at
                    ? __($l.'until', ['date' => $n->reverts_at->subMinute()->setTimezone($viewer)->locale(app()->getLocale())->translatedFormat('j F Y')])
                    : __($l.'for_good'),
                'lessons' => trans_choice($l.'lessons', $n->lessons, ['count' => $n->lessons]),
                'decision' => $n->decision,
                'decisionLabel' => __($l.'decisions.'.$n->decision),
                'told' => $n->notified_early_at !== null,
                'tellOn' => __($l.'will_tell', ['date' => $tellOn->setTimezone($viewer)->locale(app()->getLocale())->translatedFormat('j F')]),
                'problems' => $problems,
                'canKeepStudent' => $problems === 0,
                'canKeepTeacher' => $n->decision === 'pending',
            ];
        }

        return array_values(collect($groups)->sortBy('at')->all());
    }

    public function keepTeacherAction(): Action
    {
        return Action::make('keepTeacher')
            ->label(__('scheduling::dst.keep_teacher'))->link()->size('sm')->color('gray')
            ->action(function (array $arguments) {
                $this->decide([$arguments['notice'] ?? ''], 'keep_teacher');
            });
    }

    public function keepStudentAction(): Action
    {
        $l = 'scheduling::dst.';

        return Action::make('keepStudent')
            ->label(__($l.'keep_student'))->button()->size('sm')->outlined()
            ->requiresConfirmation()
            ->modalHeading(__($l.'keep_student'))
            ->modalDescription(fn (array $arguments) => $this->confirmation($arguments['notice'] ?? ''))
            ->modalSubmitActionLabel(__($l.'keep_student'))
            ->action(function (array $arguments) {
                $this->decide([$arguments['notice'] ?? ''], 'keep_student');
            });
    }

    public function keepTeacherSelectedAction(): Action
    {
        return Action::make('keepTeacherSelected')
            ->label(__('scheduling::dst.keep_teacher'))->color('gray')->size('sm')
            ->disabled(fn () => $this->selected === [])
            ->action(fn () => $this->decide($this->selected, 'keep_teacher'));
    }

    public function keepStudentSelectedAction(): Action
    {
        $l = 'scheduling::dst.';

        return Action::make('keepStudentSelected')
            ->label(__($l.'keep_student'))->size('sm')
            ->disabled(fn () => $this->selected === [])
            ->requiresConfirmation()
            ->modalHeading(__($l.'keep_student'))
            ->modalDescription(fn () => __($l.'confirm_bulk', ['count' => trans_choice($l.'selected', count($this->selected), ['count' => count($this->selected)])]))
            ->action(fn () => $this->decide($this->selected, 'keep_student'));
    }

    /** Picks every row a decision can still be made on. */
    public function selectAll(): void
    {
        $this->selected = collect($this->changes)->flatMap(fn (array $g) => $g['rows'])
            ->filter(fn (array $r) => $r['canKeepStudent'] || $r['canKeepTeacher'])->pluck('id')->values()->all();
    }

    protected function getHeaderActions(): array
    {
        $l = 'scheduling::dst.';

        return [
            Action::make('scan')->label(__($l.'scan'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(function () use ($l) {
                    $found = app(PlanDstChanges::class)->handle();
                    app(SendDstNotices::class)->handle();
                    unset($this->changes);
                    Notification::make()->success()->title(trans_choice($l.'scanned', $found, ['count' => $found]))->send();
                }),
            $this->shiftAction(),
        ];
    }

    /** Bulk rescheduling: one teacher's weekly lessons, some days, the same minutes on their clock. */
    private function shiftAction(): Action
    {
        $l = 'scheduling::dst.shift.';
        $preview = fn (Get $get) => filled($get('teacher_id')) && filled($get('from')) && filled($get('to')) && filled($get('minutes'))
            ? $this->preview($get('teacher_id'), $get('from'), $get('to'), (int) $get('minutes'))
            : null;

        return Action::make('shift')->label(__($l.'button'))->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading(__($l.'heading'))->modalDescription(__($l.'hint'))->modalWidth('lg')
            ->schema([
                Select::make('teacher_id')->label(__($l.'teacher'))->required()->searchable()->live()->options(fn () => TimeOffForm::teachers()),
                Grid::make(2)->schema([
                    DatePicker::make('from')->label(__($l.'from'))->required()->native(false)->live()
                        ->minDate(fn (Get $get) => $get('teacher_id') ? CarbonImmutable::now(app(RequestTimeOff::class)->timezone($get('teacher_id')))->toDateString() : null),
                    DatePicker::make('to')->label(__($l.'to'))->required()->native(false)->live()->afterOrEqual('from'),
                ]),
                Select::make('minutes')->label(__($l.'minutes'))->required()->native(false)->live()
                    ->options(collect(ShiftTeacherLessons::MINUTES)->mapWithKeys(fn (int $m) => [$m => __($l.'by.'.$m)])->all()),
                Callout::make(__($l.'preview'))
                    ->description(fn (Get $get) => $preview($get)['text'] ?? '')
                    ->status(fn (Get $get) => ($preview($get)['ok'] ?? true) ? 'info' : 'danger')
                    ->visible(fn (Get $get) => $preview($get) !== null),
            ])
            ->modalSubmitActionLabel(__($l.'apply'))
            ->action(function (array $data, Action $action) use ($l) {
                $this->attempt($action, function () use ($data, $l) {
                    $moved = app(ShiftTeacherLessons::class)->handle($data['teacher_id'], self::day($data['from']), self::day($data['to']), (int) $data['minutes'], (string) auth()->id());

                    return __($l.'applied', ['lessons' => trans_choice('scheduling::dst.lessons', $moved, ['count' => $moved])]);
                });
            });
    }

    /** @return array{text: string, ok: bool} */
    private function preview(string $teacherId, string $from, string $to, int $minutes): array
    {
        $l = 'scheduling::dst.shift.';
        try {
            $p = app(ShiftTeacherLessons::class)->preview($teacherId, self::day($from), self::day($to), $minutes);
        } catch (ValidationException $e) {
            return ['text' => (string) collect($e->errors())->flatten()->first(), 'ok' => false];
        }

        return match (true) {
            $p['lessons'] === 0 => ['text' => __($l.'preview_none'), 'ok' => true],
            $p['problems'] !== [] => ['text' => __($l.'preview_busy', [
                'count' => count($p['problems']),
                'dates' => collect($p['problems'])->take(4)->map(fn (array $x) => $x['start']->setTimezone($p['timezone'])->locale(app()->getLocale())->translatedFormat(Clock::pattern('l j F H:i')))->implode('، '),
            ]), 'ok' => false],
            default => ['text' => __($l.'preview_ok', [
                'lessons' => trans_choice('scheduling::dst.lessons', $p['lessons'], ['count' => $p['lessons']]),
                'students' => trans_choice($l.'students', $p['students'], ['count' => $p['students']]),
            ]), 'ok' => true],
        };
    }

    /** @param  list<string>  $ids */
    private function decide(array $ids, string $decision): void
    {
        $l = 'scheduling::dst.';
        $done = 0;
        $failed = [];
        foreach (ShiftNotice::query()->whereIn('id', $ids)->orderBy('changes_at')->get() as $notice) {
            if ($decision === 'keep_teacher' && $notice->decision !== 'pending') {
                continue;
            }
            try {
                app(DecideDstChange::class)->handle($notice, $decision, (string) auth()->id());
                $done++;
            } catch (ValidationException) {
                $failed[] = (string) DB::table('users')->where('id', $notice->student_id)->value('name');
            }
        }
        $this->selected = [];
        unset($this->changes);

        $note = Notification::make()->title(trans_choice($l.'done', $done, ['count' => $done]));
        $failed === [] ? $note->success() : $note->warning()->body(__($l.'failed', ['names' => implode('، ', array_unique($failed))]));
        $note->send();
    }

    private function confirmation(string $id): string
    {
        $n = ShiftNotice::query()->find($id);
        if ($n === null) {
            return '';
        }
        $l = 'scheduling::dst.';
        $locale = app()->getLocale();

        return __($l.'confirm_student', [
            'student' => (string) DB::table('users')->where('id', $n->student_id)->value('name'),
            'teacher' => (string) DB::table('users')->where('id', $n->teacher_id)->value('name'),
            'time' => self::weekdayTime($n->before_weekday, $n->before_time),
            'zone' => Timezones::city($n->other_tz),
            'teacher_time' => $n->changes_at->subMinutes($n->delta_min)->setTimezone($n->anchor_tz)->locale($locale)->translatedFormat(Clock::pattern('l H:i')),
            'from' => $n->changes_at->setTimezone($n->anchor_tz)->locale($locale)->translatedFormat('j F'),
            'until' => $n->reverts_at ? __($l.'until', ['date' => $n->reverts_at->subMinute()->setTimezone($n->anchor_tz)->locale($locale)->translatedFormat('j F Y')]) : __($l.'for_good'),
        ]);
    }

    /** Runs a change; a refusal keeps the dialog open with the reason. */
    private function attempt(Action $action, Closure $change): void
    {
        try {
            $message = $change();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();

            return;
        }
        unset($this->changes);
        Notification::make()->success()->title($message)->send();
    }

    /** The clock change of a zone that the lesson at $at is the first after. */
    private static function clockChange(string $zone, CarbonImmutable $at): CarbonImmutable
    {
        $moments = (new DateTimeZone($zone))->getTransitions($at->getTimestamp() - 400 * 86400, $at->getTimestamp()) ?: [];
        $last = end($moments);

        return CarbonImmutable::createFromTimestampUTC(is_array($last) ? $last['ts'] : $at->getTimestamp());
    }

    private static function weekdayTime(int $weekday, string $time): string
    {
        return CarbonImmutable::parse('2026-01-05 '.substr($time, 0, 5))->addDays($weekday - 1)->locale(app()->getLocale())->translatedFormat(Clock::pattern('l H:i'));
    }

    private static function day(string $date): string
    {
        return CarbonImmutable::parse($date)->toDateString();
    }
}
