<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * Pays for an enrollment's unpaid upcoming sessions from its credit, in
 * date order (docs/architecture/02, "حالة التمويل"): the first session that
 * cannot be covered stops the run, so credit is never spent on a later
 * lesson while an earlier one waits.
 */
final class FundSessions
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
    ) {}

    /** @return int how many sessions were funded */
    public function handle(string $enrollmentId, ?CarbonImmutable $now = null): int
    {
        $accountId = $this->enrollments->find($enrollmentId)?->creditAccountId;
        if ($accountId === null) {
            return 0;
        }

        $waiting = Session::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('status', SessionStatus::Scheduled->value)
            ->where('funding_status', FundingStatus::Unfunded->value)
            ->where('starts_at', '>', $now ?? CarbonImmutable::now())
            ->orderBy('starts_at')
            ->get();

        $funded = 0;
        foreach ($waiting as $session) {
            $ok = DB::transaction(function () use ($session, $accountId) {
                if (! $this->credits->reserve($session->id, $accountId, $session->durationMinutes(), $session->starts_at, "session:{$session->id}:reserve")) {
                    return false;
                }
                $session->funding_status = FundingStatus::Funded;
                $session->save();
                SessionEvent::record($session, SessionEventKind::DecisionLogged, ['decision' => 'funded']);

                return true;
            });
            if (! $ok) {
                break;
            }
            $funded++;
        }

        return $funded;
    }
}
