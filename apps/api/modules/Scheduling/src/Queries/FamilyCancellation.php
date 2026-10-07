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

    public function outcome(Session $session): string
    {
        [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::StudentCancel);
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
