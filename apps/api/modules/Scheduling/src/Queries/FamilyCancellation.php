<?php

namespace Modules\Scheduling\Queries;

use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Policy\PolicyEvent;

/** What cancelling a lesson does, in the family's words (no teacher pay, no scores), before they confirm. */
final class FamilyCancellation
{
    public function __construct(private readonly PolicyDecider $decider) {}

    /** A group lesson's seat ($enrollmentId: the student's course) is excused, never the lesson cancelled; its words say so. */
    public function outcome(Session $session, ?string $enrollmentId = null): string
    {
        [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::StudentCancel, null, $enrollmentId);
        if ($session->isGroup()) {
            $g = 'scheduling::group_outcomes.excuse.';

            return match (true) {
                $decision->credit === 'refund' => __($g.'refund', ['hours' => $version->policy()->get('student_notice_hours')]),
                $decision->credit === 'makeup' => __($g.'makeup'),
                $input->noticeHours < $version->policy()->get('student_notice_hours') => __($g.'late', ['hours' => $version->policy()->get('student_notice_hours')]),
                default => __($g.'quota', ['quota' => $version->policy()->get('quota_per_block')]),
            };
        }
        $policy = $version->policy();
        $l = 'scheduling::family.cancel.outcomes.';

        return match (true) {
            $session->type === SessionType::Trial => __($l.'trial'),
            $session->type === SessionType::Makeup => __($l.'makeup'),
            $decision->credit === 'refund' => __($l.'refund', ['hours' => $policy->get('student_notice_hours')]),
            $input->noticeHours < $policy->get('student_notice_hours') => __($l.'late', ['hours' => $policy->get('student_notice_hours')]),
            default => __($l.'quota', ['quota' => $policy->get('quota_per_block')]),
        };
    }
}
