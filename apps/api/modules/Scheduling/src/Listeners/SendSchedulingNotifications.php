<?php

namespace Modules\Scheduling\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Scheduling\Contracts\Events\HolidayDeclared;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\SessionTeacherSubstituted;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftProposalChangesRequested;
use Modules\Scheduling\Contracts\Events\ShiftProposalCountered;
use Modules\Scheduling\Contracts\Events\ShiftProposalRejected;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Contracts\Events\TimeOffDecided;
use Modules\Scheduling\Contracts\Events\TimeOffRequested;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Queries\SessionPeople;
use Modules\SharedKernel\Authorization\Ability;

/**
 * What the family, the teacher and the reviewers hear about lessons,
 * shifts, time off, substitutes and holidays (docs/architecture/01-features.md, section 15). Whoever made the
 * change is not told about it. The wording is in lang/{ar,en}/notifications.php.
 */
final class SendSchedulingNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly SessionPeople $sessions,
        private readonly IdentityApi $identity,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $lesson = ['student' => 'زياد', 'teacher' => 'أ. خالد', 'subject' => 'النحو', 'when' => 'الأحد 11 أكتوبر · 18:00'];
        $a = 'scheduling::notifications.audience.';
        // Each person's own signed link to the class page, filled in by Communication (Classroom registers it).
        $link = ['link' => 'https://demo.lms.test/class/…'];
        foreach ([
            ['reminder_day', 'lessons', $a.'family', ['push', 'email'], $lesson],
            ['reminder_hour', 'lessons', $a.'family', ['push', 'whatsapp'], $lesson + $link],
            ['reminder_hour_teacher', 'staff', $a.'teacher', ['push'], $lesson + $link],
            ['join_now', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + $link],
            ['teacher_join_now', 'staff', $a.'teacher', ['whatsapp', 'push'], $lesson + $link],
            ['teacher_late', 'staff', $a.'supervisors', ['push'], $lesson],
            ['session_cancelled', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['outcome' => 'وليكم حصة تعويض.']],
            ['session_cancelled_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson],
            ['session_moved', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['from' => 'السبت 10 أكتوبر · 18:00']],
            ['session_moved_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson + ['from' => 'السبت 10 أكتوبر · 18:00']],
            ['proposal_submitted', 'staff', $a.'reviewers', ['push', 'email'], ['teacher' => 'أ. سارة']],
            ['proposal_answered', 'staff', $a.'teacher', ['push', 'whatsapp'], ['outcome' => 'اتعتمد.']],
            ['timeoff_requested', 'staff', $a.'leave_managers', ['push', 'email'], ['teacher' => 'أ. خالد', 'from' => 'الخميس 8 أكتوبر', 'to' => 'السبت 10 أكتوبر', 'lessons' => '4']],
            ['timeoff_answered', 'staff', $a.'teacher', ['push', 'whatsapp'], ['from' => 'الخميس 8 أكتوبر', 'to' => 'السبت 10 أكتوبر', 'outcome' => 'اتوافق عليها.']],
            ['substitute_family', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['original' => 'أ. سارة']],
            ['teacher_back_family', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson],
            ['substitute_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson + ['original' => 'أ. سارة']],
            ['holiday_family', 'lessons', $a.'family', ['whatsapp', 'email', 'push'], ['name' => 'عيد الأضحى', 'from' => 'الثلاثاء 26 مايو', 'to' => 'الجمعة 29 مايو', 'student' => 'زياد', 'count' => '2']],
            ['holiday_teacher', 'staff', $a.'teacher', ['push', 'email'], ['name' => 'عيد الأضحى', 'from' => 'الثلاثاء 26 مايو', 'to' => 'الجمعة 29 مايو', 'count' => '6']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "scheduling.{$name}", category: $category,
                title: "scheduling::notifications.{$name}.title", body: "scheduling::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function cancelled(SessionCancelled $event): void
    {
        if ($event->reason === 'holiday') {
            return;     // the holiday's own message says it once (holidayDeclared)
        }
        $s = $this->sessions->of($event->sessionId);
        if ($s === null) {
            return;
        }
        $credit = DB::table('sessions')->where('id', $event->sessionId)->value('decision');
        $credit = json_decode((string) $credit, true)['credit'] ?? 'none';
        $outcome = $event->reason === 'unpaid' ? 'unpaid' : (in_array($credit, ['makeup', 'refund', 'forfeit', 'consumed'], true) ? $credit : 'none');
        $data = self::lessonData($s) + ['outcome' => new Translatable("scheduling::notifications.outcomes.{$outcome}")];
        $action = ['screen' => 'session', 'id' => $s['session_id']];

        $this->notifications->send(new Message('scheduling.session_cancelled', $this->without($s['family'], $event->actorId), $data, $action, "cancelled:{$s['session_id']}"));
        $this->notifications->send(new Message('scheduling.session_cancelled_teacher', $this->without([$s['teacher_id']], $event->actorId), $data, $action, "cancelled:{$s['session_id']}"));
    }

    public function rescheduled(SessionRescheduled $event): void
    {
        $s = $this->sessions->of($event->sessionId);
        if ($s === null) {
            return;
        }
        $data = self::lessonData($s) + ['from' => CarbonImmutable::parse($event->fromUtc)];
        $action = ['screen' => 'session', 'id' => $s['session_id']];
        $key = "moved:{$s['session_id']}:{$s['starts_at']->getTimestamp()}";

        $this->notifications->send(new Message('scheduling.session_moved', $this->without($s['family'], $event->actorId), $data, $action, $key));
        $this->notifications->send(new Message('scheduling.session_moved_teacher', $this->without([$s['teacher_id']], $event->actorId), $data, $action, $key));
    }

    public function proposalSubmitted(ShiftProposalSubmitted $event): void
    {
        $this->notifications->send(new Message('scheduling.proposal_submitted', $this->without($this->identity->usersWithAbility(Ability::ReviewShifts), $event->actorId), [
            'teacher' => (string) DB::table('users')->where('id', $event->teacherId)->value('name'),
        ], ['screen' => 'shift_proposal', 'id' => $event->proposalId]));
    }

    public function proposalAnswered(ShiftProposalApproved|ShiftProposalRejected|ShiftProposalChangesRequested|ShiftProposalCountered $event): void
    {
        $answer = match (true) {
            $event instanceof ShiftProposalApproved => 'approved',
            $event instanceof ShiftProposalRejected => 'rejected',
            $event instanceof ShiftProposalChangesRequested => 'changes_requested',
            default => 'countered',
        };

        $this->notifications->send(new Message('scheduling.proposal_answered', $this->without([$event->teacherId], $event->actorId), [
            'outcome' => new Translatable("scheduling::notifications.answers.{$answer}"),
        ], ['screen' => 'shift_proposal', 'id' => $event->proposalId]));
    }

    public function timeOffRequested(TimeOffRequested $event): void
    {
        $timeOff = TeacherTimeOff::query()->find($event->timeOffId);
        if ($timeOff === null) {
            return;
        }
        $lessons = Session::query()->where('teacher_id', $timeOff->teacher_id)->where('status', 'scheduled')
            ->where('starts_at', '<', $timeOff->ends_at)->where('ends_at', '>', $timeOff->starts_at)->count();

        $this->notifications->send(new Message('scheduling.timeoff_requested', $this->without($this->identity->usersWithAbility(Ability::ManageTimeOff), $event->actorId), self::period($timeOff) + [
            'teacher' => (string) DB::table('users')->where('id', $timeOff->teacher_id)->value('name'),
            'lessons' => (string) $lessons,
        ], ['screen' => 'time_off', 'id' => $timeOff->id]));
    }

    public function timeOffDecided(TimeOffDecided $event): void
    {
        $timeOff = TeacherTimeOff::query()->find($event->timeOffId);
        if ($timeOff === null) {
            return;
        }
        $answer = $event->status === 'approved' && $timeOff->requested_by !== $timeOff->teacher_id ? 'recorded' : $event->status;

        $this->notifications->send(new Message('scheduling.timeoff_answered', $this->without([$timeOff->teacher_id], $event->actorId), self::period($timeOff) + [
            'outcome' => new Translatable("scheduling::notifications.leave_answers.{$answer}"),
        ], ['screen' => 'time_off', 'id' => $timeOff->id], "timeoff:{$timeOff->id}:{$event->status}"));
    }

    public function substituted(SessionTeacherSubstituted $event): void
    {
        $s = $this->sessions->of($event->sessionId);
        if ($s === null) {
            return;
        }
        $original = DB::table('sessions as s')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 's.academy_id')->on('u.id', '=', 's.original_teacher_id'))
            ->where('s.id', $event->sessionId)->value('u.name');
        $data = self::lessonData($s) + ['original' => (string) $original];
        $action = ['screen' => 'session', 'id' => $s['session_id']];
        $key = "substitute:{$s['session_id']}:{$event->toTeacherId}";

        $this->notifications->send(new Message($original ? 'scheduling.substitute_family' : 'scheduling.teacher_back_family', $this->without($s['family'], $event->actorId), $data, $action, $key));
        $this->notifications->send(new Message('scheduling.substitute_teacher', $this->without([$event->toTeacherId], $event->actorId), $data + ['original' => (string) ($original ?? '')], $action, $key));
    }

    /** One message per family and per teacher, with how many of their lessons the holiday took. */
    public function holidayDeclared(HolidayDeclared $event): void
    {
        $holiday = AcademyHoliday::query()->find($event->holidayId);
        if ($holiday === null || $event->sessionIds === []) {
            return;
        }
        $period = ['name' => $holiday->name, 'from' => new Day($holiday->first_day->toDateString()), 'to' => new Day($holiday->last_day->toDateString())];
        $action = ['screen' => 'holiday', 'id' => $holiday->id];

        $students = [];
        $teachers = [];
        foreach ($event->sessionIds as $id) {
            $s = $this->sessions->of($id);
            if ($s === null) {
                continue;
            }
            $teachers[$s['teacher_id']] = ($teachers[$s['teacher_id']] ?? 0) + 1;
            if ($s['student_id'] !== null) {
                $students[$s['student_id']] ??= ['name' => $s['student'], 'family' => $s['family'], 'count' => 0];
                $students[$s['student_id']]['count']++;
            }
        }

        foreach ($students as $studentId => $st) {
            $this->notifications->send(new Message('scheduling.holiday_family', $this->without($st['family'], $event->actorId),
                $period + ['student' => $st['name'], 'count' => (string) $st['count']], $action, "holiday:{$holiday->id}:{$studentId}"));
        }
        foreach ($teachers as $teacherId => $count) {
            $this->notifications->send(new Message('scheduling.holiday_teacher', $this->without([$teacherId], $event->actorId),
                $period + ['count' => (string) $count], $action, "holiday:{$holiday->id}:{$teacherId}"));
        }
    }

    /** @return array{from: Day|CarbonImmutable, to: Day|CarbonImmutable} whole days as days on the teacher's clock, hours as instants */
    private static function period(TeacherTimeOff $timeOff): array
    {
        return $timeOff->all_day
            ? ['from' => new Day($timeOff->starts_at->setTimezone($timeOff->timezone)->toDateString()), 'to' => new Day($timeOff->lastDay()->toDateString())]
            : ['from' => $timeOff->starts_at, 'to' => $timeOff->ends_at];
    }

    /** @return array<string, mixed> */
    public static function lessonData(array $s): array
    {
        return ['student' => $s['student'], 'teacher' => $s['teacher'], 'subject' => $s['subject'], 'when' => $s['starts_at']];
    }

    /** @param  list<string>  $ids */
    private function without(array $ids, ?string $actorId): array
    {
        return array_values(array_filter($ids, fn ($id) => $id !== $actorId));
    }
}
