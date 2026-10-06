<?php

namespace Modules\Scheduling\Actions\Policy;

use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Policy\Decision;
use Modules\Scheduling\Domain\Policy\PolicyInput;

/**
 * Writes a policy decision on the session (docs/architecture/02, section 4:
 * "كل قرار بيتسجل على الحصة"): the version, the facts, the outcome, who
 * asked and why. The caller saves the session inside its transaction.
 */
final class RecordPolicyDecision
{
    /**
     * @param  array<string, mixed>  $context  by, reason, note, waived
     * @return array<string, mixed> the decision as stored on the session
     */
    public function handle(Session $session, Decision $decision, PolicyInput $input, PolicyVersion $version, array $context, ?string $actorId): array
    {
        $stored = array_filter($context, fn ($v) => $v !== null && $v !== false) + [
            'rule' => $decision->rule,
            'credit' => $decision->credit,
            'teacher_pay_pct' => $decision->teacherPayPct,
            'policy_version' => $version->version,
            'notice_hours' => round($input->noticeHours, 1),
        ];

        $session->decision = $stored;
        $session->policy_version_id = $version->id;

        SessionEvent::record($session, SessionEventKind::PolicyApplied, [
            'input' => $input->toArray(),
            'decision' => $decision->toArray(),
            'policy_version' => $version->version,
        ] + array_filter($context, fn ($v) => $v !== null && $v !== false), $actorId);

        return $stored;
    }
}
