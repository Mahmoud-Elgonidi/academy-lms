<?php

namespace Modules\Scheduling\Domain\Policy;

use LogicException;
use Modules\Scheduling\Domain\Policy\Conditions\NoticeAtLeast;
use Modules\Scheduling\Domain\Policy\Conditions\NoticeBelow;
use Modules\Scheduling\Domain\Policy\Conditions\QuotaLeft;
use Modules\Scheduling\Domain\Policy\Conditions\SessionTypeIs;

/**
 * The default rule table (docs/architecture/02, section 4, "جدول القواعد
 * الافتراضي"), in order: the first rule that matches decides. Numbers follow
 * the document; 20 and 21 are the academy's own reschedules and cancellations.
 * Lateness and technical problems (rules 6, 10–14) are decided when a
 * session under review is resolved.
 */
final class PolicyEngine
{
    public static function decide(PolicyInput $input, PolicySettings $settings): Decision
    {
        foreach (self::rules() as $rule) {
            if ($rule->matches($input, $settings)) {
                return $rule->decide($settings, $input);
            }
        }

        throw new LogicException("No policy rule for [{$input->event->value}].");
    }

    /** @return list<Rule> */
    public static function rules(): array
    {
        $studentNotice = new NoticeAtLeast('student_notice_hours');
        $studentLate = new NoticeBelow('student_notice_hours');
        $teacherNotice = new NoticeAtLeast('teacher_notice_hours');
        $teacherLate = new NoticeBelow('teacher_notice_hours');
        $trial = new SessionTypeIs('trial');
        $makeup = new SessionTypeIs('makeup');
        $latePay = fn (PolicySettings $s, PolicyInput $in) => $in->noticeHours < $s->get('student_notice_hours') ? $s->get('late_cancel_teacher_pay_pct') : 0;

        return [
            // Reschedules.
            new Rule(18, PolicyEvent::StudentReschedule, [$trial], fn () => new Decision(18, credit: 'keep')),
            new Rule(1, PolicyEvent::StudentReschedule, [$studentNotice, new QuotaLeft], fn () => new Decision(1, credit: 'keep', countsQuota: true)),
            new Rule(1, PolicyEvent::StudentReschedule, [$studentLate], fn () => Decision::refuse(1, 'too_late')),
            new Rule(1, PolicyEvent::StudentReschedule, [new QuotaLeft(false)], fn () => Decision::refuse(1, 'quota')),
            new Rule(20, PolicyEvent::AcademyReschedule, [], fn () => new Decision(20, credit: 'keep')),

            // The student cancels.
            new Rule(18, PolicyEvent::StudentCancel, [$trial], fn () => new Decision(18, credit: 'none')),
            new Rule(17, PolicyEvent::StudentCancel, [$makeup], fn (PolicySettings $s, PolicyInput $in) => new Decision(17, credit: 'forfeit', teacherPayPct: $latePay($s, $in))),
            new Rule(2, PolicyEvent::StudentCancel, [$studentNotice, new QuotaLeft], fn () => new Decision(2, credit: 'refund', countsQuota: true)),
            new Rule(3, PolicyEvent::StudentCancel, [$studentNotice, new QuotaLeft(false)], fn () => new Decision(3, credit: 'forfeit', kpi: 'late_student_cancel', kpiSeverity: 'minor', countsQuota: true)),
            new Rule(4, PolicyEvent::StudentCancel, [$studentLate], fn (PolicySettings $s) => new Decision(4, credit: 'forfeit', teacherPayPct: $s->get('late_cancel_teacher_pay_pct'), kpi: 'late_student_cancel', kpiSeverity: 'minor', countsQuota: true)),

            // Attendance.
            new Rule(5, PolicyEvent::StudentAbsent, [], fn (PolicySettings $s) => new Decision(5, credit: 'consumed', teacherPayPct: $s->get('student_absent_teacher_pay_pct'), kpi: 'student_absent', kpiSeverity: 'minor')),
            new Rule(9, PolicyEvent::TeacherAbsent, [], fn () => new Decision(9, credit: 'makeup', kpi: 'teacher_absent', kpiSeverity: 'critical')),

            // The teacher cancels.
            new Rule(7, PolicyEvent::TeacherCancel, [$teacherNotice], fn () => new Decision(7, credit: 'makeup', kpi: 'teacher_cancel', kpiSeverity: 'minor')),
            new Rule(8, PolicyEvent::TeacherCancel, [$teacherLate], fn () => new Decision(8, credit: 'makeup', kpi: 'teacher_late_cancel', kpiSeverity: 'major')),

            // The academy.
            new Rule(15, PolicyEvent::Closure, [], fn () => new Decision(15, credit: 'refund')),
            new Rule(16, PolicyEvent::Unpaid, [], fn () => new Decision(16, credit: 'none')),
            new Rule(19, PolicyEvent::Emergency, [], fn () => new Decision(19, credit: 'refund')),
            new Rule(21, PolicyEvent::AcademyCancel, [], fn () => new Decision(21, credit: 'makeup')),
        ];
    }
}
