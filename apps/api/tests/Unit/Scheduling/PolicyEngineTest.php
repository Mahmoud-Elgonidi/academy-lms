<?php

use Modules\Scheduling\Domain\Policy\PolicyEngine;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings;

function decideFor(PolicyEvent $event, float $notice = 48, string $type = 'regular', int $quotaUsed = 0, array $settings = [])
{
    return PolicyEngine::decide(new PolicyInput($event, $notice, $type, $quotaUsed), PolicySettings::fromArray($settings));
}

it('gives the credit back when the student cancels in time and within the quota (rule 2)', function () {
    $d = decideFor(PolicyEvent::StudentCancel, notice: 12);

    expect([$d->rule, $d->credit, $d->teacherPayPct, $d->countsQuota, $d->kpi])->toBe([2, 'refund', 0, true, null]);
});

it('keeps the credit once the quota is used up, even with notice (rule 3)', function () {
    $d = decideFor(PolicyEvent::StudentCancel, notice: 30, quotaUsed: 2);

    expect([$d->rule, $d->credit, $d->teacherPayPct, $d->kpi])->toBe([3, 'forfeit', 0, 'late_student_cancel']);
});

it('keeps the credit and pays the teacher half for a late cancellation (rule 4)', function () {
    $d = decideFor(PolicyEvent::StudentCancel, notice: 11.9);

    expect([$d->rule, $d->credit, $d->teacherPayPct])->toBe([4, 'forfeit', 50]);
});

it('follows the academy\'s numbers', function () {
    $d = decideFor(PolicyEvent::StudentCancel, notice: 5, settings: ['student_notice_hours' => 4, 'quota_per_block' => 3], quotaUsed: 2);

    expect([$d->rule, $d->credit])->toBe([2, 'refund']);
});

it('lets the student reschedule in time and within the quota, and refuses otherwise (rule 1)', function () {
    expect(decideFor(PolicyEvent::StudentReschedule, notice: 24)->allowed)->toBeTrue()
        ->and(decideFor(PolicyEvent::StudentReschedule, notice: 24)->credit)->toBe('keep')
        ->and(decideFor(PolicyEvent::StudentReschedule, notice: 3)->refusal)->toBe('too_late')
        ->and(decideFor(PolicyEvent::StudentReschedule, notice: 24, quotaUsed: 2)->refusal)->toBe('quota')
        ->and(decideFor(PolicyEvent::AcademyReschedule, notice: 1, quotaUsed: 9)->allowed)->toBeTrue();
});

it('owes the student a makeup when the teacher cancels, more serious when late (rules 7 and 8)', function () {
    expect([decideFor(PolicyEvent::TeacherCancel, notice: 30)->rule, decideFor(PolicyEvent::TeacherCancel, notice: 30)->credit])->toBe([7, 'makeup'])
        ->and(decideFor(PolicyEvent::TeacherCancel, notice: 3)->kpiSeverity)->toBe('major')
        ->and(decideFor(PolicyEvent::TeacherAbsent)->kpiSeverity)->toBe('critical');
});

it('never makes up for a makeup, and a trial costs nothing (rules 17 and 18)', function () {
    expect(decideFor(PolicyEvent::StudentCancel, notice: 48, type: 'makeup')->credit)->toBe('forfeit')
        ->and(decideFor(PolicyEvent::StudentCancel, notice: 2, type: 'makeup')->teacherPayPct)->toBe(50)
        ->and(decideFor(PolicyEvent::StudentCancel, notice: 2, type: 'trial')->credit)->toBe('none')
        ->and(decideFor(PolicyEvent::StudentReschedule, notice: 1, type: 'trial')->allowed)->toBeTrue();
});

it('decides the academy\'s own cases', function () {
    expect(decideFor(PolicyEvent::Closure)->credit)->toBe('refund')
        ->and(decideFor(PolicyEvent::Emergency)->credit)->toBe('refund')
        ->and(decideFor(PolicyEvent::Unpaid)->credit)->toBe('none')
        ->and(decideFor(PolicyEvent::AcademyCancel)->credit)->toBe('makeup')
        ->and(decideFor(PolicyEvent::StudentAbsent)->teacherPayPct)->toBe(100);
});

it('turns a forfeit into a refund when a manager waives it', function () {
    expect(decideFor(PolicyEvent::StudentCancel, notice: 1)->waived()->credit)->toBe('refund')
        ->and(decideFor(PolicyEvent::TeacherCancel)->waived()->credit)->toBe('makeup');
});

it('refuses numbers out of range', function () {
    expect(fn () => PolicySettings::fromArray(['completion_pct' => 120]))->toThrow(InvalidArgumentException::class)
        ->and(PolicySettings::fromArray(['student_notice_hours' => '6'])->get('student_notice_hours'))->toBe(6)
        ->and(PolicySettings::fromArray([])->attendance()->completionShare)->toBe(0.75);
});
