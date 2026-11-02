<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\DTOs\LessonAtRisk;
use Modules\Billing\Contracts\UnpaidLessons;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;

/**
 * Billing's question for its collection timeline (J5a): the first lesson of
 * these courses that rule 16 (CancelUnpaidSessions) would cancel unpaid, and
 * when it would: the same lessons it looks at (scheduled, of a course, not
 * funded, not let go ahead unpaid by a manager), at its start less the
 * academy's unpaid hours. None while rule 16 is off.
 */
final class UnpaidLessonsQuery implements UnpaidLessons
{
    public function firstAtRisk(array $enrollmentIds, CarbonImmutable $now): ?LessonAtRisk
    {
        $hours = (int) PolicyVersion::settingsInForce()->get('unpaid_cancel_hours');
        if ($hours === 0 || $enrollmentIds === []) {
            return null;
        }
        $session = Session::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->where('status', SessionStatus::Scheduled->value)
            ->where('funding_status', FundingStatus::Unfunded->value)
            ->where('starts_at', '>', $now->addHours($hours))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('session_events as ev')
                ->whereColumn('ev.session_id', 'sessions.id')->whereColumn('ev.academy_id', 'sessions.academy_id')
                ->where('ev.kind', SessionEventKind::DecisionLogged->value)
                ->whereRaw("ev.data->>'decision' = 'allow_unfunded'"))
            ->orderBy('starts_at')
            ->first();

        return $session === null ? null : new LessonAtRisk($session->id, (string) $session->enrollment_id, $session->starts_at, $session->starts_at->subHours($hours));
    }
}
