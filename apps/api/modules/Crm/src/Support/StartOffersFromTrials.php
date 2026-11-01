<?php

namespace Modules\Crm\Support;

use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\DTOs\StartOffer;
use Modules\Scheduling\Contracts\StartOffers;

/**
 * The offer after a free trial that goes with a learner and subject (docs/
 * architecture/02, section 27, J2 and J3): the latest trial in that subject
 * with an offer made to this payer (the lead's contact). Billing says whether
 * it is still open.
 */
final class StartOffersFromTrials implements StartOffers
{
    public function forLearner(string $learnerId, string $subjectId, string $payerId): ?StartOffer
    {
        $row = DB::table('lead_trials as t')
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 't.academy_id')->on('l.id', '=', 't.lead_id'))
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 't.academy_id')->on('s.id', '=', 't.session_id'))
            ->where('l.learner_id', $learnerId)->where('l.contact_id', $payerId)->where('s.subject_id', $subjectId)
            ->whereNotNull('t.offer_coupon_id')
            ->orderByDesc('t.starts_at')->first(['t.offer_coupon_id', 't.session_id']);

        return $row === null ? null : new StartOffer((string) $row->offer_coupon_id, (string) $row->session_id);
    }
}
