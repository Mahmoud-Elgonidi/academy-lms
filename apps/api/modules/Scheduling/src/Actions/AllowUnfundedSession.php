<?php

namespace Modules\Scheduling\Actions;

use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/** A manager lets an unpaid session go ahead as an exception (default rule 16 would cancel it). */
final class AllowUnfundedSession
{
    public function handle(Session $session, string $actorId, ?string $note = null): void
    {
        if ($session->funding_status !== FundingStatus::Unfunded) {
            return;
        }

        SessionEvent::record($session, SessionEventKind::DecisionLogged, array_filter([
            'decision' => 'allow_unfunded',
            'note' => $note,
        ]), $actorId);
    }
}
