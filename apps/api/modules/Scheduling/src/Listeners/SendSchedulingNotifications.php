<?php

namespace Modules\Scheduling\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\DTOs\WeekdayTime;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\Events\EnrollmentEnded;
use Modules\Enrollment\Contracts\Events\EnrollmentPaused;
use Modules\Enrollment\Contracts\Events\EnrollmentResumed;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Contracts\Events\DstShiftDecided;
use Modules\Scheduling\Contracts\Events\DstShiftUpcoming;
use Modules\Scheduling\Contracts\Events\HolidayDeclared;
use Modules\Scheduling\Contracts\Events\LessonDisputed;
use Modules\Scheduling\Contracts\Events\LessonDisputeDecided;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\SessionTeacherSubstituted;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftProposalChangesRequested;
use Modules\Scheduling\Contracts\Events\ShiftProposalCountered;
use Modules\Scheduling\Contracts\Events\ShiftProposalRejected;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Contracts\Events\SlotTimesShifted;
use Modules\Scheduling\Contracts\Events\TeacherLessonsShifted;
use Modules\Scheduling\Contracts\Events\TimeOffDecided;
use Modules\Scheduling\Contracts\Events\TimeOffRequested;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionDispute;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Queries\SessionPeople;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Timezones;

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
        private readonly PeopleApi $people,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $lesson = ['student' => 'زياد', 'teacher' => 'أ. خالد', 'subject' => 'النحو', 'when' => 'الأحد 11 أكتوبر · 6:00 م'];
        $a = 'scheduling::notifications.audience.';
        // Each person's own signed link to the class page, filled in by Communication (Classroom registers it).
        $link = ['link' => 'https://demo.lms.test/class/…'];
        foreach ([
            ['reminder_day', 'lessons', $a.'family', ['push', 'email'], $lesson],
            ['reminder_hour', 'lessons', $a.'family', ['push', 'whatsapp'], $lesson + $link],
            ['reminder_hour_teacher', 'staff', $a.'teacher', ['push'], $lesson + $link],
            ['reminder_soon', 'lessons', $a.'family', ['push'], $lesson + $link],
            ['reminder_soon_teacher', 'staff', $a.'teacher', ['push'], $lesson + $link],
            ['join_now', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + $link],
            ['teacher_join_now', 'staff', $a.'teacher', ['whatsapp', 'push'], $lesson + $link],
            ['teacher_late', 'staff', $a.'supervisors', ['push'], $lesson],
            ['session_cancelled', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['outcome' => 'ولكم حصة تعويضية.']],
            ['session_cancelled_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson],
            ['trial_booked', 'lessons', $a.'family', ['whatsapp', 'email', 'push'], $lesson + $link],
            ['trial_booked_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson],
            ['session_moved', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['from' => 'السبت 10 أكتوبر · 6:00 م']],
            ['session_moved_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson + ['from' => 'السبت 10 أكتوبر · 6:00 م']],
            ['proposal_submitted', 'staff', $a.'reviewers', ['push', 'email'], ['teacher' => 'أ. سارة']],
            ['proposal_answered', 'staff', $a.'teacher', ['push', 'whatsapp'], ['outcome' => 'اعتُمد.']],
            ['timeoff_requested', 'staff', $a.'leave_managers', ['push', 'email'], ['teacher' => 'أ. خالد', 'from' => 'الخميس 8 أكتوبر', 'to' => 'السبت 10 أكتوبر', 'lessons' => '4']],
            ['timeoff_answered', 'staff', $a.'teacher', ['push', 'whatsapp'], ['from' => 'الخميس 8 أكتوبر', 'to' => 'السبت 10 أكتوبر', 'outcome' => 'تمت الموافقة عليها.']],
            ['substitute_family', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['original' => 'أ. سارة']],
            ['teacher_back_family', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson],
            ['substitute_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson + ['original' => 'أ. سارة']],
            ['holiday_family', 'lessons', $a.'family', ['whatsapp', 'email', 'push'], ['name' => 'عيد الأضحى', 'from' => 'الثلاثاء 26 مايو', 'to' => 'الجمعة 29 مايو', 'student' => 'زياد', 'count' => '2']],
            ['holiday_teacher', 'staff', $a.'teacher', ['push', 'email'], ['name' => 'عيد الأضحى', 'from' => 'الثلاثاء 26 مايو', 'to' => 'الجمعة 29 مايو', 'count' => '6']],
            ['dst_family', 'lessons', $a.'family', ['whatsapp', 'push'], ['student' => 'زياد', 'subject' => 'النحو', 'teacher' => 'أ. خالد', 'when' => 'الجمعة 30 أكتوبر · 7:00 م', 'before' => 'الجمعة 6:00 م', 'after' => 'الجمعة 7:00 م', 'zone' => 'الرياض', 'cause' => 'القاهرة']],
            ['dst_kept_family', 'lessons', $a.'family', ['whatsapp', 'push'], ['student' => 'زياد', 'time' => 'الجمعة 6:00 م', 'zone' => 'الرياض']],
            ['lessons_shifted_family', 'lessons', $a.'family', ['whatsapp', 'push'], ['student' => 'زياد', 'subject' => 'النحو', 'time' => 'الأحد 7:00 م', 'zone' => 'الرياض', 'from' => 'الأحد 1 نوفمبر', 'until' => 'السبت 28 نوفمبر']],
            ['lessons_shifted_family_open', 'lessons', $a.'family', ['whatsapp', 'push'], ['student' => 'زياد', 'subject' => 'النحو', 'time' => 'الأحد 7:00 م', 'zone' => 'الرياض', 'from' => 'الأحد 1 نوفمبر']],
            ['lessons_shifted_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], ['student' => 'زياد', 'subject' => 'النحو', 'time' => 'الأحد 7:00 م', 'from' => 'الأحد 1 نوفمبر', 'until' => 'السبت 28 نوفمبر', 'why' => '']],
            ['lessons_shifted_teacher_open', 'staff', $a.'teacher', ['push', 'whatsapp'], ['student' => 'زياد', 'subject' => 'النحو', 'time' => 'الأحد 7:00 م', 'from' => 'الأحد 1 نوفمبر', 'why' => '']],
            ['dispute_opened', 'staff', $a.'session_managers', ['push', 'email'], $lesson + ['side' => 'الأسرة', 'reason' => 'لم يحضر المعلّم', 'before' => 'تمت']],
            ['dispute_decided', 'lessons', $a.'family', ['push', 'whatsapp'], $lesson + ['outcome' => 'قُبل الاعتراض.', 'after' => 'غياب المعلّم', 'answer' => 'راجعنا الحضور، ولكم حصة تعويضية.']],
            ['dispute_decided_teacher', 'staff', $a.'teacher', ['push'], $lesson + ['outcome' => 'قُبل الاعتراض.', 'after' => 'تمت', 'answer' => 'راجعنا الحضور وسُجّلت لك.']],
            ['lesson_corrected', 'lessons', $a.'family', ['push'], $lesson + ['after' => 'تمت']],
            ['lesson_corrected_teacher', 'staff', $a.'teacher', ['push'], $lesson + ['after' => 'غياب المعلّم']],
            ['teacher_lessons_shifted', 'staff', $a.'teacher', ['push', 'whatsapp', 'email'], ['from' => 'الأحد 1 نوفمبر', 'until' => 'السبت 28 نوفمبر', 'shift' => 'متأخرًا بساعة', 'count' => '12']],
            ['course_paused_family', 'lessons', $a.'family', ['whatsapp', 'push', 'email'], ['student' => 'زياد', 'subject' => 'النحو', 'from' => 'الثلاثاء 20 أكتوبر', 'until' => 'الثلاثاء 3 نوفمبر']],
            ['course_paused_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], ['student' => 'زياد', 'subject' => 'النحو', 'from' => 'الثلاثاء 20 أكتوبر', 'until' => 'الثلاثاء 3 نوفمبر']],
            ['course_resumed_family', 'lessons', $a.'family', ['whatsapp', 'push'], ['student' => 'زياد', 'subject' => 'النحو']],
            ['course_resumed_teacher', 'staff', $a.'teacher', ['push'], ['student' => 'زياد', 'subject' => 'النحو']],
            ['course_ended_family', 'lessons', $a.'family', ['whatsapp', 'push', 'email'], ['student' => 'زياد', 'subject' => 'النحو', 'on' => 'الأحد 1 نوفمبر']],
            ['course_ended_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], ['student' => 'زياد', 'subject' => 'النحو', 'on' => 'الأحد 1 نوفمبر']],
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
        if ($event->reason === 'holiday' || in_array($event->reason, CancelSession::COURSE_STOPPED, true)) {
            return;     // the holiday's, or the course's pause or end, says it once (holidayDeclared, coursePaused, courseEnded)
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

        // Each family of a group lesson reads its own child's name only.
        foreach ($this->sessions->seats($event->sessionId) as $seat) {
            $this->notifications->send(new Message('scheduling.session_cancelled', $this->without($seat['family'], $event->actorId),
                self::lessonData($seat) + ['outcome' => $data['outcome']], $action, "cancelled:{$s['session_id']}"));
        }
        $this->notifications->send(new Message('scheduling.session_cancelled_teacher', $this->without([$s['teacher_id']], $event->actorId), self::teacherData($s) + ['outcome' => $data['outcome']], $action, "cancelled:{$s['session_id']}"));
    }

    /** A family or a teacher disputed a lesson: whoever manages lessons decides it. */
    public function disputed(LessonDisputed $event): void
    {
        $dispute = SessionDispute::query()->find($event->disputeId);
        $s = $this->sessions->of($event->sessionId, $dispute?->student_id);
        if ($s === null || $dispute === null) {
            return;
        }

        $this->notifications->send(new Message('scheduling.dispute_opened', $this->without($this->identity->usersWithAbility(Ability::ManageSessions), $dispute->raised_by), self::lessonData($s) + [
            'side' => new Translatable('scheduling::disputes.sides.'.$dispute->side),
            'reason' => new Translatable("scheduling::disputes.reasons.{$dispute->side}.{$dispute->reason}"),
            'before' => new Translatable('scheduling::disputes.outcomes.'.$dispute->outcome_before),
        ], ['screen' => 'dispute', 'id' => $dispute->id], "dispute:{$dispute->id}"));
    }

    /** Whoever disputed reads the academy's answer; when the outcome changed, the other side hears what it is now. */
    public function disputeDecided(LessonDisputeDecided $event): void
    {
        $dispute = SessionDispute::query()->find($event->disputeId);
        $s = $this->sessions->of($event->sessionId, $dispute?->student_id);
        if ($s === null || $dispute === null) {
            return;
        }
        $data = self::lessonData($s) + ['after' => new Translatable('scheduling::disputes.outcomes.'.$event->outcomeAfter)];
        $answer = $data + ['outcome' => new Translatable('scheduling::notifications.dispute_answers.'.$event->status), 'answer' => (string) $dispute->decision_note];
        $family = $this->without($s['family'], $dispute->decided_by);
        $teacher = $this->without([$s['teacher_id']], $dispute->decided_by);
        $action = ['screen' => 'session', 'id' => $s['session_id']];
        $key = "dispute:{$dispute->id}:decided";

        [$raiser, $other] = $dispute->side === 'family'
            ? [['scheduling.dispute_decided', $family], ['scheduling.lesson_corrected_teacher', $teacher]]
            : [['scheduling.dispute_decided_teacher', $teacher], ['scheduling.lesson_corrected', $family]];
        $this->notifications->send(new Message($raiser[0], $raiser[1], $answer, $action, $key));
        if ($event->status === 'upheld') {
            $this->notifications->send(new Message($other[0], $other[1], $data, $action, $key));
        }
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

        foreach ($this->sessions->seats($event->sessionId) as $seat) {
            $this->notifications->send(new Message('scheduling.session_moved', $this->without($seat['family'], $event->actorId), self::lessonData($seat) + ['from' => $data['from']], $action, $key));
        }
        $this->notifications->send(new Message('scheduling.session_moved_teacher', $this->without([$s['teacher_id']], $event->actorId), self::teacherData($s) + ['from' => $data['from']], $action, $key));
    }

    /** A free trial: the family gets the time and each person their own link to the class; the teacher hears of it. */
    public function trialBooked(TrialLessonBooked $event): void
    {
        $s = $this->sessions->of($event->sessionId);
        if ($s === null) {
            return;
        }
        $action = ['screen' => 'session', 'id' => $s['session_id']];
        $key = "trial:{$s['session_id']}";

        $this->notifications->send(new Message('scheduling.trial_booked', $s['family'], self::lessonData($s), $action, $key));
        $this->notifications->send(new Message('scheduling.trial_booked_teacher', $this->without([$s['teacher_id']], $event->actorId), self::lessonData($s), $action, $key));
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

        foreach ($this->sessions->seats($event->sessionId) as $seat) {
            $this->notifications->send(new Message($original ? 'scheduling.substitute_family' : 'scheduling.teacher_back_family', $this->without($seat['family'], $event->actorId),
                self::lessonData($seat) + ['original' => $data['original']], $action, $key));
        }
        $this->notifications->send(new Message('scheduling.substitute_teacher', $this->without([$event->toTeacherId], $event->actorId), self::teacherData($s) + ['original' => (string) ($original ?? '')], $action, $key));
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
            $seats = $this->sessions->seats($id);
            if ($seats === []) {
                continue;
            }
            $teachers[$seats[0]['teacher_id']] = ($teachers[$seats[0]['teacher_id']] ?? 0) + 1;
            foreach ($seats as $s) {
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

    /** The family's new hour at a clock change, 14 and 2 days before. */
    public function dstUpcoming(DstShiftUpcoming $event): void
    {
        $notice = ShiftNotice::query()->find($event->noticeId);
        if ($notice === null || ! $notice->movesForFamily()) {
            return;
        }
        $s = $this->sessions->of((string) Session::query()->where('enrollment_id', $notice->enrollment_id)->where('starts_at', $notice->changes_at)->value('id'));
        $family = [$notice->student_id, ...$this->people->guardiansOf($notice->student_id)];
        $day = fn (int $weekday, string $time) => CarbonImmutable::parse('2026-01-05 '.substr($time, 0, 5))->addDays($weekday - 1);   // a Monday, then the weekday

        $this->notifications->send(new Message('scheduling.dst_family', $family, [
            'student' => (string) DB::table('users')->where('id', $notice->student_id)->value('name'),
            'subject' => $s['subject'] ?? '',
            'teacher' => (string) DB::table('users')->where('id', $notice->teacher_id)->value('name'),
            'when' => $notice->changes_at,
            'before' => self::weekdayTime($day($notice->before_weekday, $notice->before_time)),
            'after' => self::weekdayTime($day($notice->after_weekday, $notice->after_time)),
            'zone' => self::city($notice->other_tz),
            'cause' => self::city($notice->cause_tz),
        ], ['screen' => 'session', 'id' => $s['session_id'] ?? null], "dst:{$notice->id}:{$event->stage}"));
    }

    /** The academy kept the student's hour after the family had been told it would move. */
    public function dstDecided(DstShiftDecided $event): void
    {
        $notice = ShiftNotice::query()->find($event->noticeId);
        if ($notice === null || $event->decision !== 'keep_student' || $notice->notified_early_at === null) {
            return;
        }
        $day = CarbonImmutable::parse('2026-01-05 '.substr($notice->before_time, 0, 5))->addDays($notice->before_weekday - 1);
        $this->notifications->send(new Message('scheduling.dst_kept_family', $this->without([$notice->student_id, ...$this->people->guardiansOf($notice->student_id)], $event->actorId), [
            'student' => (string) DB::table('users')->where('id', $notice->student_id)->value('name'),
            'time' => self::weekdayTime($day),
            'zone' => self::city($notice->other_tz),
        ], ['screen' => 'session', 'id' => null], "dst:{$notice->id}:kept"));
    }

    /**
     * A weekly time moved for a period. Keeping a student's hour through a
     * clock change: the teacher hears the new time on their clock (the
     * family's does not move). An academy move: the family hears theirs (the
     * teacher hears the whole move once, teacherShifted).
     */
    public function slotShifted(SlotTimesShifted $event): void
    {
        $first = Session::query()->where('slot_id', $event->slotId)->where('status', 'scheduled')->where('starts_at', '>=', $event->fromUtc)->orderBy('starts_at')->first();
        if ($first === null) {
            return;
        }
        $s = $this->sessions->of($first->id);
        $teacherTz = (string) (DB::table('users')->where('id', $event->teacherId)->value('timezone') ?? 'UTC');
        $studentTz = (string) (DB::table('users')->where('id', $event->studentId)->value('timezone') ?? $teacherTz);
        $open = $event->untilUtc === null ? '_open' : '';
        $period = ['from' => new Day(CarbonImmutable::parse($event->fromUtc)->setTimezone($teacherTz)->toDateString())]
            + ($event->untilUtc ? ['until' => new Day(CarbonImmutable::parse($event->untilUtc)->subMinute()->setTimezone($teacherTz)->toDateString())] : []);
        $common = ['student' => $s['student'] ?? '', 'subject' => $s['subject'] ?? ''];
        $action = ['screen' => 'session', 'id' => $first->id];
        $key = "shifted:{$event->slotId}";

        if ($event->reason === 'dst') {
            $this->notifications->send(new Message("scheduling.lessons_shifted_teacher{$open}", $this->without([$event->teacherId], $event->actorId), $common + $period + [
                'time' => self::weekdayTime($first->starts_at->setTimezone($teacherTz)),
                'why' => new Translatable('scheduling::notifications.shift_reasons.dst'),
            ], $action, $key));

            return;
        }
        // A group's weekly time: each seat's family on its own, on its student's clock.
        foreach ($first->group_id === null ? [$s] : $this->sessions->seats($first->id) as $seat) {
            $tz = $first->group_id === null ? $studentTz : (string) (DB::table('users')->where('id', $seat['student_id'])->value('timezone') ?? $teacherTz);
            $this->notifications->send(new Message("scheduling.lessons_shifted_family{$open}", $this->without($seat['family'] ?? [], $event->actorId), ['student' => $seat['student'] ?? '', 'subject' => $seat['subject'] ?? ''] + $period + [
                'time' => self::weekdayTime($first->starts_at->setTimezone($tz)),
                'zone' => self::city($tz),
            ], $action, $key));
        }
    }

    /** Every weekly lesson of a teacher moved for some days: they hear it once. */
    public function teacherShifted(TeacherLessonsShifted $event): void
    {
        $this->notifications->send(new Message('scheduling.teacher_lessons_shifted', $this->without([$event->teacherId], $event->actorId), [
            'from' => new Day($event->firstDay),
            'until' => new Day($event->lastDay),
            'shift' => new Translatable('scheduling::dst.shift.by.'.$event->minutes),
            'count' => (string) $event->lessons,
        ], ['screen' => 'session', 'id' => null], "shifted:{$event->teacherId}:{$event->firstDay}:{$event->minutes}"));
    }

    /** A course was paused (or its pause made longer): the family and its teacher hear the days once. */
    public function coursePaused(EnrollmentPaused $event): void
    {
        $course = $this->course($event->enrollmentId);
        if ($course === null) {
            return;
        }
        $data = $course['data'] + ['from' => new Day($event->from), 'until' => new Day($event->until)];
        $key = "course:{$event->enrollmentId}:paused:{$event->from}:{$event->until}";

        $this->notifications->send(new Message('scheduling.course_paused_family', $this->without($course['family'], $event->actorId), $data, ['screen' => 'session', 'id' => null], $key));
        $this->notifications->send(new Message('scheduling.course_paused_teacher', $this->without([$course['teacher_id']], $event->actorId), $data, ['screen' => 'session', 'id' => null], $key));
    }

    /** A paused course is on again (or its pause called off): the family and its teacher hear it once. */
    public function courseResumed(EnrollmentResumed $event): void
    {
        $course = $this->course($event->enrollmentId);
        if ($course === null) {
            return;
        }
        $key = "course:{$event->enrollmentId}:resumed:".CarbonImmutable::now()->toDateString();

        $this->notifications->send(new Message('scheduling.course_resumed_family', $this->without($course['family'], $event->actorId), $course['data'], ['screen' => 'session', 'id' => null], $key));
        $this->notifications->send(new Message('scheduling.course_resumed_teacher', $this->without([$course['teacher_id']], $event->actorId), $course['data'], ['screen' => 'session', 'id' => null], $key));
    }

    /** A course ends: the family (the minutes left stay in the balance) and its teacher (the times are free) hear it once. */
    public function courseEnded(EnrollmentEnded $event): void
    {
        $course = $this->course($event->enrollmentId);
        if ($course === null) {
            return;
        }
        $data = $course['data'] + ['on' => new Day($event->on)];
        $key = "course:{$event->enrollmentId}:ended";

        $this->notifications->send(new Message('scheduling.course_ended_family', $this->without($course['family'], $event->actorId), $data, ['screen' => 'session', 'id' => null], $key));
        $this->notifications->send(new Message('scheduling.course_ended_teacher', $this->without([$course['teacher_id']], $event->actorId), $data, ['screen' => 'session', 'id' => null], $key));
    }

    /** @return array{family: list<string>, teacher_id: string, data: array<string, mixed>}|null the student and their guardians, the teacher, and the names */
    private function course(string $enrollmentId): ?array
    {
        $course = app(EnrollmentApi::class)->find($enrollmentId);
        if ($course === null) {
            return null;
        }
        $subject = DB::table('subjects')->where('id', $course->subjectId)->first(['name_ar', 'name_en']);

        return [
            'family' => [$course->studentId, ...$this->people->guardiansOf($course->studentId)],
            'teacher_id' => $course->teacherId,
            'data' => [
                'student' => (string) DB::table('users')->where('id', $course->studentId)->value('name'),
                'subject' => new Localized((string) ($subject->name_ar ?? ''), (string) ($subject->name_en ?? $subject->name_ar ?? '')),
            ],
        ];
    }

    private static function weekdayTime(CarbonImmutable $local): WeekdayTime
    {
        return new WeekdayTime($local);
    }

    private static function city(string $timezone): Localized
    {
        return new Localized(Timezones::city($timezone, 'ar'), Timezones::city($timezone, 'en'));
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

    /** The same for the teacher (and the supervisors): a group lesson named as the group, its subject and weekly times, never its first student. */
    public static function teacherData(array $s): array
    {
        return array_replace(self::lessonData($s), ($s['group_name'] ?? null) !== null ? ['student' => $s['group_name']] : []);
    }

    /** @param  list<string>  $ids */
    private function without(array $ids, ?string $actorId): array
    {
        return array_values(array_filter($ids, fn ($id) => $id !== $actorId));
    }
}
