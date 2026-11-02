<?php

namespace Modules\Billing\Contracts;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\DTOs\LessonAtRisk;

/**
 * Billing's own question about lessons, which Scheduling answers (Billing
 * knows nothing of lessons; docs/architecture/04): the first lesson of these
 * courses that rule 16 would cancel for not being paid, so the collection
 * timeline can say "pay now to keep it" a day before (J5a). Without
 * Scheduling, none.
 */
interface UnpaidLessons
{
    /** @param  list<string>  $enrollmentIds */
    public function firstAtRisk(array $enrollmentIds, CarbonImmutable $now): ?LessonAtRisk;
}
