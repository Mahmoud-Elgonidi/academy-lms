<?php

namespace Modules\Scheduling\Contracts;

use Modules\Scheduling\Contracts\DTOs\StartOffer;

/**
 * The offer a learner has after a free trial in a subject (docs/architecture/02,
 * section 27, J2 and J3), for the course the family starts on its own: the
 * CRM knows which offer goes with which learner and subject, and binds this.
 * Without the CRM there is none.
 */
interface StartOffers
{
    /** The learner's latest offer after a trial in this subject, made for this payer (open or not); null when none. */
    public function forLearner(string $learnerId, string $subjectId, string $payerId): ?StartOffer;
}
