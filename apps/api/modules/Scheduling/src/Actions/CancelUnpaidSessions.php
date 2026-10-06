<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;

/**
 * Rule 16: an enrolled student's session still unpaid N hours before it
 * starts (24 by default; 0 turns it off) is cancelled by the system, so the
 * teacher's time is not spent on a lesson nobody paid for. A manager can let
 * one go ahead first ("allow unpaid"). Sessions booked outside an
 * enrollment (no invoice to pay) are left to the live board.
 */
final class CancelUnpaidSessions
{
    public function __construct(
        private readonly FundSessions $fund,
        private readonly CancelSession $cancel,
    ) {}

    /** @return int how many sessions were cancelled */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $hours = PolicyVersion::settingsInForce()->get('unpaid_cancel_hours');
        if ($hours === 0) {
            return 0;
        }

        $due = fn () => Session::query()
            ->whereNotNull('enrollment_id')
            ->where('status', SessionStatus::Scheduled->value)
            ->where('funding_status', FundingStatus::Unfunded->value)
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->addHours($hours))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('session_events as ev')
                ->whereColumn('ev.session_id', 'sessions.id')->whereColumn('ev.academy_id', 'sessions.academy_id')
                ->where('ev.kind', SessionEventKind::DecisionLogged->value)
                ->whereRaw("ev.data->>'decision' = 'allow_unfunded'"))
            ->orderBy('starts_at')
            ->get();

        // Credit may have arrived since the last funding run.
        foreach ($due()->pluck('enrollment_id')->unique() as $enrollmentId) {
            $this->fund->handle($enrollmentId, $now);
        }

        $cancelled = 0;
        foreach ($due() as $session) {
            $this->cancel->handle($session, 'system', 'unpaid', null, null, at: $now);
            $cancelled++;
        }

        return $cancelled;
    }
}
