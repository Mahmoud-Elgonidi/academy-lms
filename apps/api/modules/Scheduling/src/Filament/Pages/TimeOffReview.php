<?php

namespace Modules\Scheduling\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\SubstituteTeacher;
use Modules\Scheduling\Actions\TimeOff\AssignSubstitutes;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\WithdrawTimeOff;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\Scheduling\Queries\SubstituteCandidates;
use Modules\Scheduling\Queries\TimeOffCover;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * One teacher's time off: the answer, and cover for each of its lessons
 * (docs/architecture/01, flow "ز"): a substitute from the same engine as
 * finding a teacher, or a makeup for the student. Times on the viewer's
 * clock, with the substitute's own beside each name.
 */
final class TimeOffReview extends Page
{
    protected string $view = 'scheduling::filament.time-off.review';

    protected static ?string $slug = 'time-off/{timeOff}';

    protected static bool $shouldRegisterNavigation = false;

    public string $timeOffId;

    /** @var array<string, string> session id => the substitute chosen in its row */
    public array $pick = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageTimeOff->value);
    }

    public function mount(string $timeOff): void
    {
        $this->timeOffId = TeacherTimeOff::query()->findOrFail($timeOff)->id;
    }

    #[Computed]
    public function timeOff(): TeacherTimeOff
    {
        return TeacherTimeOff::query()->findOrFail($this->timeOffId);
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::timeoff.review.title', ['teacher' => $this->teacherName()]);
    }

    /** The lessons are on the viewer's clock, the teacher's beside each one when it differs. */
    public function getSubheading(): string|Htmlable|null
    {
        return ZoneHint::lessonsWithTeacher();
    }

    public function getBreadcrumbs(): array
    {
        return [TimeOffRequests::getUrl() => __('scheduling::timeoff.admin.nav'), $this->teacherName()];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function details(): array
    {
        $t = $this->timeOff;

        return [
            'period' => TimeOffView::period($t),
            'zone' => Timezones::city($t->timezone),
            'reason' => __('scheduling::timeoff.reasons.'.$t->reason),
            'note' => $t->note,
            'status' => $t->status->value,
            'statusLabel' => __('scheduling::timeoff.statuses.'.$t->status->value),
            'decision' => $t->decision_note,
            'byAcademy' => $t->requested_by !== $t->teacher_id,
            'approved' => $t->status === TimeOffStatus::Approved && ! $t->ends_at->isPast(),
        ];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function lessons(): array
    {
        $t = $this->timeOff;
        $tz = app(ViewerTimezone::class)->effective();
        $lessons = app(TimeOffCover::class)->lessons($t);
        $open = array_values(array_filter($lessons, fn (Session $s) => TimeOffView::state($s, $t) === 'needs_cover' && $s->starts_at->isFuture()));
        $candidates = app(SubstituteCandidates::class)->forSessions($open, (string) auth()->id());
        $originals = DB::table('users')->whereIn('id', array_filter(array_map(fn (Session $s) => $s->original_teacher_id, $lessons)))->pluck('name', 'id');

        $theirs = ! Timezones::sameClock($t->timezone, $tz);

        return array_map(function (Session $s) use ($t, $tz, $theirs, $candidates) {
            $state = TimeOffView::state($s, $t);
            $start = $s->starts_at->setTimezone($tz);

            return [
                'id' => $s->id,
                'day' => TimeOffView::day($start),
                'time' => TimeOffView::clock($start).' – '.TimeOffView::clock($s->ends_at->setTimezone($tz)),
                // The same lesson on the teacher's clock, when it keeps another one than the viewer's.
                'teacher_time' => $theirs ? __('scheduling::timeoff.review.teacher_time', [
                    'time' => TimeOffView::clock($s->starts_at->setTimezone($t->timezone)),
                    'zone' => Timezones::city($t->timezone),
                ]) : null,
                'student' => (string) $s->getAttribute('student_name'),
                'subject' => app()->getLocale() === 'ar' ? $s->getAttribute('subject_ar') : $s->getAttribute('subject_en'),
                'state' => $state,
                'stateLabel' => __('scheduling::timeoff.review.states.'.$state, ['name' => $s->getAttribute('teacher_name')]),
                'why' => $state === 'cancelled' ? __('scheduling::policy.reasons.'.($s->decision['reason'] ?? 'other')) : null,
                'future' => $s->starts_at->isFuture(),
                'candidates' => $candidates[$s->id] ?? null,
            ];
        }, $lessons);
    }

    public function assign(string $sessionId): void
    {
        $this->authorizeCover();
        $teacherId = $this->pick[$sessionId] ?? null;
        if (! $teacherId) {
            return;
        }
        $session = $this->ownLesson($sessionId);
        app(SubstituteTeacher::class)->handle($session, $teacherId, (string) auth()->id());
        unset($this->pick[$sessionId], $this->lessons);
        Notification::make()->success()->title(__('scheduling::timeoff.review.assigned', ['name' => DB::table('users')->where('id', $teacherId)->value('name')]))->send();
    }

    public function makeup(string $sessionId): void
    {
        $this->authorizeCover();
        app(CancelSession::class)->handle($this->ownLesson($sessionId), 'academy', 'teacher_leave', null, (string) auth()->id());
        unset($this->lessons);
        Notification::make()->success()->title(__('scheduling::timeoff.review.made_up'))->send();
    }

    protected function getHeaderActions(): array
    {
        $l = 'scheduling::timeoff.review.';
        $pending = fn () => $this->timeOff->status === TimeOffStatus::Pending && ! $this->timeOff->ends_at->isPast();

        return [
            Action::make('coverAll')->label(__($l.'cover_all'))->icon(Heroicon::OutlinedSparkles)
                ->visible(fn () => $this->details['approved'] && collect($this->lessons)->contains(fn ($r) => $r['state'] === 'needs_cover' && $r['future']))
                ->requiresConfirmation()->modalDescription(__($l.'cover_all_hint'))
                ->action(function () use ($l) {
                    $done = app(AssignSubstitutes::class)->handle($this->timeOff, (string) auth()->id());
                    unset($this->lessons);
                    Notification::make()->{$done['covered'] > 0 ? 'success' : 'warning'}()->title(trans_choice($l.'covered_all', $done['covered'], $done))->send();
                }),
            Action::make('approve')->label(__($l.'approve'))->color('success')->icon(Heroicon::OutlinedCheck)->visible($pending)
                ->schema([Textarea::make('note')->label(__($l.'note'))->rows(2)])
                ->action(function (array $data) use ($l) {
                    app(DecideTimeOff::class)->handle($this->timeOff, true, $data['note'] ?? null, (string) auth()->id());
                    $this->refreshAll();
                    Notification::make()->success()->title(__($l.'approved'))->send();
                }),
            Action::make('reject')->label(__($l.'reject'))->color('danger')->outlined()->visible($pending)
                ->schema([Textarea::make('note')->label(__($l.'note'))->rows(2)->required()])
                ->action(function (array $data) use ($l) {
                    app(DecideTimeOff::class)->handle($this->timeOff, false, $data['note'], (string) auth()->id());
                    $this->refreshAll();
                    Notification::make()->success()->title(__($l.'rejected'))->send();
                }),
            Action::make('callOff')->label(__($l.'call_off'))->color('gray')
                ->visible(fn () => $this->details['approved'])
                ->requiresConfirmation()->modalDescription(__($l.'call_off_hint'))
                ->action(function () use ($l) {
                    app(WithdrawTimeOff::class)->handle($this->timeOff, (string) auth()->id(), asManager: true);
                    $this->refreshAll();
                    Notification::make()->success()->title(__($l.'called_off'))->send();
                }),
        ];
    }

    private function teacherName(): string
    {
        return (string) DB::table('users')->where('id', $this->timeOff->teacher_id)->value('name');
    }

    private function refreshAll(): void
    {
        unset($this->timeOff, $this->details, $this->lessons);
    }

    private function authorizeCover(): void
    {
        abort_unless(Gate::allows(Ability::ManageTimeOff->value) && $this->details['approved'], 403);
    }

    /** A lesson of this leave, nothing else. */
    private function ownLesson(string $sessionId): Session
    {
        $session = Session::query()->findOrFail($sessionId);
        abort_unless($session->teacher_id === $this->timeOff->teacher_id && $this->timeOff->covers($session->starts_at, $session->ends_at), 404);

        return $session;
    }
}
