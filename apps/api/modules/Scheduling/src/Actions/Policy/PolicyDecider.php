<?php

namespace Modules\Scheduling\Actions\Policy;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Policy\Decision;
use Modules\Scheduling\Domain\Policy\PolicyEngine;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\Scheduling\Domain\Policy\PolicyInput;

/**
 * Gathers the facts about a session (notice, type, the student's
 * cancellations and reschedules so far in the current block) and asks the
 * engine, under the policy version in force.
 */
final class PolicyDecider
{
    /** @return array{0: Decision, 1: PolicyInput, 2: PolicyVersion} */
    public function decide(Session $session, PolicyEvent $event, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $version = PolicyVersion::current();
        $input = new PolicyInput(
            event: $event,
            noticeHours: ($session->starts_at->getTimestamp() - $at->getTimestamp()) / 3600,
            sessionType: $session->type->value,
            quotaUsed: $session->enrollment_id ? $this->quotaUsed($session->enrollment_id) : 0,
        );

        return [PolicyEngine::decide($input, $version->policy()), $input, $version];
    }

    /**
     * Student cancellations and reschedules that counted since the current
     * block began (the latest paid lot of the enrollment's credit account).
     */
    public function quotaUsed(string $enrollmentId): int
    {
        $accountId = DB::table('enrollments')->where('id', $enrollmentId)->value('credit_account_id');
        $blockStart = $accountId ? DB::table('credit_lots')->where('account_id', $accountId)->where('source', 'purchase')->max('created_at') : null;

        return DB::table('session_events as ev')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'ev.academy_id')->on('s.id', '=', 'ev.session_id'))
            ->where('s.enrollment_id', $enrollmentId)
            ->where('ev.kind', SessionEventKind::PolicyApplied->value)
            ->whereRaw("(ev.data->'decision'->>'counts_quota')::boolean is true")
            ->when($blockStart, fn ($q) => $q->where('ev.occurred_at', '>=', $blockStart))
            ->count();
    }
}
