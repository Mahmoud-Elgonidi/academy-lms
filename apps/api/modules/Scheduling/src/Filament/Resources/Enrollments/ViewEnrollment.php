<?php

namespace Modules\Scheduling\Filament\Resources\Enrollments;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\ReassignTeacher;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Domain\Availability\IntervalSet;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\Scheduling\Filament\PolicyPreview;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Queries\EnrollmentAccountQuery;
use Modules\Scheduling\Queries\Models\EnrolledStudent;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The student account for one enrollment: credit left in sessions and
 * minutes, what is booked, the ledger line by line, and the invoices. From
 * here a coordinator cancels or moves a lesson (under the policy), books a
 * makeup, or moves the student to another teacher.
 *
 * @property EnrolledStudent $record
 */
final class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected string $view = 'scheduling::filament.account';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // From a shift proposal review: "move this student to another teacher from …".
        if (is_string($from = request()->query('reassign')) && Gate::allows(Ability::ManageSessions->value)) {
            $this->defaultAction = 'reassign';
            $this->defaultActionArguments = ['from' => $from];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bookMakeup')
                ->label(__('scheduling::enrollments.makeup.action'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn () => Gate::allows(Ability::ManageSessions->value) && $this->account()['makeup']['minutes'] > 0)
                ->modalHeading(__('scheduling::enrollments.makeup.title'))
                ->modalDescription(fn () => __('scheduling::enrollments.makeup.hint', [
                    'minutes' => $this->account()['makeup']['minutes'],
                    'date' => ($this->account()['fmt'])($this->account()['makeup']['expires_at'], 'j F'),
                ]))
                ->schema(fn () => $this->timePicker(until: $this->account()['makeup']['expires_at']))
                ->modalSubmitActionLabel(__('scheduling::enrollments.makeup.submit'))
                ->action(fn (array $data, Action $action) => $this->attempt($action, function () use ($data) {
                    $owed = DB::table('credit_lots')->where('account_id', $this->record->credit_account_id)->where('source', 'makeup')
                        ->where('remaining_min', '>', 0)->orderBy('expires_at')->value('source_id');
                    $session = app(BookMakeup::class)->handle($this->record->id, CarbonImmutable::parse($data['starts_at']), (string) auth()->id(), $owed);

                    return __('scheduling::enrollments.makeup.done', ['date' => ($this->account()['fmt'])($session->starts_at)]);
                })),
            Action::make('reassign')
                ->label(__('scheduling::enrollments.reassign.action'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->visible(fn () => Gate::allows(Ability::ManageSessions->value))
                ->modalHeading(__('scheduling::enrollments.reassign.title'))
                ->modalDescription(__('scheduling::enrollments.reassign.hint'))
                ->fillForm(fn (array $arguments) => ['from' => $arguments['from'] ?? CarbonImmutable::now()->addDay()->toDateString()])
                ->schema([
                    Select::make('teacher')->label(__('scheduling::enrollments.reassign.teacher'))
                        ->options(fn () => $this->otherTeachers())->required()->searchable()->native(false),
                    DatePicker::make('from')->label(__('scheduling::enrollments.reassign.from'))->required()->native(false)->minDate(CarbonImmutable::now()->toDateString()),
                ])
                ->modalSubmitActionLabel(__('scheduling::enrollments.reassign.submit'))
                ->action(fn (array $data, Action $action) => $this->attempt($action, function () use ($data) {
                    $result = app(ReassignTeacher::class)->handle($this->record->id, $data['teacher'], CarbonImmutable::parse($data['from']), (string) auth()->id());
                    $this->record->refresh();

                    return __('scheduling::enrollments.reassign.done', ['moved' => $result['moved']])
                        .($result['retimed'] > 0 ? ' '.__('scheduling::enrollments.reassign.retimed', ['n' => $result['retimed']]) : '');
                })),
            // The student's age, which picks the look of their own area (/my): kids, middle school, teens.
            Action::make('studentLook')
                ->label(__('scheduling::enrollments.look.action'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn () => Gate::allows(Ability::ManageSessions->value))
                ->modalHeading(__('scheduling::enrollments.look.title'))
                ->modalDescription(__('scheduling::enrollments.look.hint'))
                ->fillForm(function () {
                    $profile = app(PeopleApi::class)->studentProfile($this->record->student_id);

                    return ['birth_date' => $profile->birthDate?->toDateString(), 'stage' => $profile->chosenStage?->value ?? 'auto'];
                })
                ->schema([
                    DatePicker::make('birth_date')->label(__('scheduling::enrollments.look.birth_date'))->native(false)->maxDate(CarbonImmutable::now()->toDateString()),
                    Radio::make('stage')->label(__('scheduling::enrollments.look.stage'))->required()
                        ->options(collect(['auto', ...array_map(fn (LearnerStage $s) => $s->value, LearnerStage::cases())])->mapWithKeys(fn (string $s) => [$s => __('scheduling::enrollments.look.stages.'.$s)])->all())
                        ->descriptions(collect(['auto', ...array_map(fn (LearnerStage $s) => $s->value, LearnerStage::cases())])->mapWithKeys(fn (string $s) => [$s => __('scheduling::enrollments.look.hints.'.$s)])->all()),
                ])
                ->action(fn (array $data, Action $action) => $this->attempt($action, function () use ($data) {
                    app(PeopleApi::class)->saveStudentProfile(
                        $this->record->student_id,
                        filled($data['birth_date'] ?? null) ? CarbonImmutable::parse($data['birth_date']) : null,
                        $data['stage'] === 'auto' ? null : LearnerStage::from($data['stage']),
                    );

                    return __('scheduling::enrollments.look.done', ['stage' => __('scheduling::enrollments.look.stages.'.app(PeopleApi::class)->studentProfile($this->record->student_id)->stage->value)]);
                })),
        ];
    }

    public function cancelSessionAction(): Action
    {
        return Action::make('cancelSession')
            ->label(__('scheduling::enrollments.row.cancel'))
            ->link()->size('sm')->color('danger')
            ->authorize(fn () => Gate::allows(Ability::ManageSessions->value))
            ->modalHeading(fn (array $arguments) => __('scheduling::enrollments.row.cancel_title', ['when' => ($this->account()['fmt'])($this->lesson($arguments)->starts_at)]))
            ->fillForm(['by' => 'student', 'reason' => 'illness', 'waive' => false])
            ->schema(fn (array $arguments) => PolicyPreview::cancelFields($this->lesson($arguments)))
            ->modalSubmitActionLabel(__('scheduling::board.cancel.submit'))
            ->action(fn (array $data, array $arguments, Action $action) => $this->attempt($action, function () use ($data, $arguments) {
                app(CancelSession::class)->handle($this->lesson($arguments), $data['by'], $data['reason'], $data['note'] ?? null, (string) auth()->id(), (bool) ($data['waive'] ?? false));

                return __('scheduling::board.done.cancelled');
            }));
    }

    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label(__('scheduling::enrollments.row.reschedule'))
            ->link()->size('sm')
            ->authorize(fn () => Gate::allows(Ability::ManageSessions->value))
            ->modalHeading(fn (array $arguments) => __('scheduling::enrollments.row.reschedule_title', ['when' => ($this->account()['fmt'])($this->lesson($arguments)->starts_at)]))
            ->fillForm(fn (array $arguments) => ['by' => 'student', 'date' => $this->lesson($arguments)->starts_at->setTimezone($this->studentTz())->toDateString(), 'waive' => false])
            ->schema(fn (array $arguments) => [
                ToggleButtons::make('by')->label(__('scheduling::policy.asked_by.label'))
                    ->options(['student' => __('scheduling::policy.asked_by.student'), 'academy' => __('scheduling::policy.asked_by.academy')])
                    ->inline()->required()->live(),
                Callout::make(__('scheduling::policy.preview.title'))
                    ->description(fn (Get $get) => PolicyPreview::describe($this->lesson($arguments), $get('by') === 'academy' ? PolicyEvent::AcademyReschedule : PolicyEvent::StudentReschedule))
                    ->color(fn (Get $get) => PolicyPreview::decision($this->lesson($arguments), $get('by') === 'academy' ? PolicyEvent::AcademyReschedule : PolicyEvent::StudentReschedule)->allowed ? 'info' : 'warning'),
                Toggle::make('waive')->label(__('scheduling::policy.waive.reschedule'))->helperText(__('scheduling::policy.waive.hint'))
                    ->visible(fn (Get $get) => $get('by') !== 'academy' && ! PolicyPreview::decision($this->lesson($arguments), PolicyEvent::StudentReschedule)->allowed),
                ...$this->timePicker(own: $this->lesson($arguments)),
            ])
            ->modalSubmitActionLabel(__('scheduling::enrollments.row.reschedule_submit'))
            ->action(fn (array $data, array $arguments, Action $action) => $this->attempt($action, function () use ($data, $arguments) {
                $session = app(RescheduleSession::class)->handle($this->lesson($arguments), CarbonImmutable::parse($data['starts_at']), $data['by'], (string) auth()->id(), (bool) ($data['waive'] ?? false));

                return __('scheduling::enrollments.row.rescheduled', ['when' => ($this->account()['fmt'])($session->starts_at)]);
            }));
    }

    public function getTitle(): string|Htmlable
    {
        return $this->account()['header']['student'] ?? __('scheduling::enrollments.model');
    }

    /** @return array<string, mixed> */
    public function account(): array
    {
        $q = app(EnrollmentAccountQuery::class);
        $h = $q->header($this->record->id);
        $tz = app(ViewerTimezone::class)->effective();
        $studentTz = $h['student_tz'] ?? $tz;
        $minutes = (int) ($h['session_minutes'] ?? 0);
        $makeup = $q->makeupOwed($h['credit_account_id']);
        $remaining = max(0, (int) $h['available_min'] + (int) $h['reserved_min'] - $makeup['minutes']);
        $fmt = fn ($at, string $pattern = 'D j M · H:i') => $at ? CarbonImmutable::parse($at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat($pattern) : null;

        return [
            'header' => $h,
            'tz' => $tz,
            'studentTz' => $studentTz,
            'subject' => app()->getLocale() === 'ar' ? $h['subject_ar'] : $h['subject_en'],
            'plan' => app()->getLocale() === 'ar' ? $h['plan_ar'] : $h['plan_en'],
            'price' => $h['amount_minor'] !== null ? Money::of((int) $h['amount_minor'], $h['currency'])->format() : null,
            'sessionsLeft' => $minutes > 0 ? intdiv($remaining, $minutes) : 0,
            'remainingMin' => $remaining,
            'reservedMin' => (int) $h['reserved_min'],
            'availableMin' => (int) $h['available_min'],
            'block' => (int) ($h['sessions_per_block'] ?? 0),
            'renewAt' => (int) ($h['renew_at_sessions'] ?? 0),
            'counts' => $q->counts($this->record->id),
            'weekly' => $q->weeklyTimes($this->record->id, $studentTz),
            'upcoming' => array_map(fn ($s) => [
                'id' => $s->id,
                'when' => $fmt($s->starts_at),
                'status' => $s->status,
                'funding' => $s->funding_status,
                'type' => $s->type,
                'moved' => (bool) $s->is_detached,
                'future' => CarbonImmutable::parse($s->starts_at)->isFuture(),
            ], $q->upcoming($this->record->id)),
            'statement' => array_map(fn ($e) => [
                'when' => $fmt($e->occurred_at, 'j M · H:i'),
                'type' => $e->type,
                'mode' => json_decode($e->meta ?? '{}', true)['mode'] ?? null,
                'minutes' => (int) $e->minutes,
                'sign' => match ($e->type) {
                    'grant' => '+', 'expire', 'consume' => '−', default => ''
                },
                'available' => (int) $e->available_after,
                'reserved' => (int) $e->reserved_after,
                'ref' => $e->invoice ?? ($e->session_at ? $fmt($e->session_at, 'D j M · H:i') : null),
                'source' => $e->source,
            ], $q->statement($h['credit_account_id'])),
            'invoices' => array_map(fn ($i) => [
                'id' => $i->id,
                'number' => $i->number,
                'status' => $i->status === 'issued' && $i->due_at && CarbonImmutable::parse($i->due_at)->isPast() ? 'overdue' : $i->status,
                'total' => Money::of((int) $i->total_minor, $i->currency)->format(),
                'issued' => $fmt($i->issued_at, 'j M Y'),
                'url' => route('filament.academy.resources.invoices.view', ['record' => $i->id]),
            ], $q->invoices($h['subscription_id'])),
            'makeup' => $makeup,
            'expiry' => $q->nextExpiry($h['credit_account_id']),
            'fmt' => $fmt,
        ];
    }

    /**
     * A day and a free start time with the enrollment's teacher, on the
     * student's clock (the teacher's beside it), on a 15-minute grid.
     *
     * @return list<mixed>
     */
    private function timePicker(?string $until = null, ?Session $own = null): array
    {
        $tz = $this->studentTz();

        return [
            DatePicker::make('date')->label(__('scheduling::enrollments.picker.date', ['city' => Timezones::city($tz)]))
                ->required()->native(false)->live()
                ->minDate(CarbonImmutable::now($tz)->toDateString())
                ->maxDate($until ? CarbonImmutable::parse($until)->setTimezone($tz)->toDateString() : CarbonImmutable::now($tz)->addWeeks(8)->toDateString()),
            Select::make('starts_at')->label(__('scheduling::enrollments.picker.time'))
                ->options(fn (Get $get) => $get('date') ? $this->freeStarts($get('date'), $own) : [])
                ->placeholder(fn (Get $get) => match (true) {
                    ! $get('date') => __('scheduling::enrollments.picker.pick_day'),
                    $this->freeStarts($get('date'), $own) === [] => __('scheduling::enrollments.picker.none'),
                    default => __('scheduling::enrollments.picker.pick_time'),
                })
                ->required()->native(false),
        ];
    }

    /** @return array<string, string> UTC start => "16:30 · Cairo 18:30" */
    private function freeStarts(string $date, ?Session $own): array
    {
        $tz = $this->studentTz();
        $teacherTz = DB::table('users')->where('id', $this->record->teacher_id)->value('timezone') ?? 'UTC';
        $duration = (int) (DB::table('recurring_slots')->where('enrollment_id', $this->record->id)->value('duration_min') ?? 30);
        $from = CarbonImmutable::parse($date, $tz)->startOfDay();
        $to = $from->addDay();

        $availability = app(AvailabilityQuery::class)->forTeachers([$this->record->teacher_id], $from->utc(), $to->utc())[$this->record->teacher_id] ?? null;
        if ($availability === null) {
            return [];
        }
        $free = $own === null ? $availability->free
            : new IntervalSet(array_merge($availability->free->intervals, [[$own->starts_at->getTimestamp(), $own->ends_at->getTimestamp()]]));

        $options = [];
        for ($t = max($from->getTimestamp(), CarbonImmutable::now()->addMinutes(15)->getTimestamp()); $t + $duration * 60 <= $to->getTimestamp(); $t += 900) {
            $t -= $t % 900;
            if ($availability->shifts->contains($t, $t + $duration * 60) && $free->contains($t, $t + $duration * 60)) {
                $at = CarbonImmutable::createFromTimestamp($t, 'UTC');
                $options[$at->toIso8601String()] = $at->setTimezone($tz)->format('H:i').' · '.Timezones::city($teacherTz).' '.$at->setTimezone($teacherTz)->format('H:i');
            }
        }

        return $options;
    }

    /** @return array<string, string> other active teachers of this subject */
    private function otherTeachers(): array
    {
        return DB::table('teacher_subjects as ts')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'ts.academy_id')->on('u.id', '=', 'ts.teacher_id'))
            ->leftJoin('teacher_profiles as tp', fn ($j) => $j->on('tp.academy_id', '=', 'ts.academy_id')->on('tp.user_id', '=', 'ts.teacher_id'))
            ->where('ts.subject_id', $this->record->subject_id)
            ->where('ts.teacher_id', '!=', $this->record->teacher_id)
            ->where(fn ($q) => $q->whereNull('tp.status')->orWhere('tp.status', 'active'))
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.timezone'])
            ->mapWithKeys(fn ($t) => [$t->id => $t->name.' · '.Timezones::city($t->timezone ?? 'UTC')])
            ->all();
    }

    private function studentTz(): string
    {
        return DB::table('users')->where('id', $this->record->student_id)->value('timezone') ?? app(ViewerTimezone::class)->effective();
    }

    /** @param  array<string, mixed>  $arguments */
    private function lesson(array $arguments): Session
    {
        return Session::query()->with('participants')->where('enrollment_id', $this->record->id)->findOrFail($arguments['session'] ?? null);
    }

    /** Runs the change; a refusal keeps the dialog open with the reason. */
    private function attempt(Action $action, Closure $change): void
    {
        try {
            $message = $change();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();

            return;
        }
        Notification::make()->success()->title($message)->send();
    }
}
