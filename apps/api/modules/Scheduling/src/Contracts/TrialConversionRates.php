<?php

namespace Modules\Scheduling\Contracts;

/**
 * How often each teacher's trial lessons become subscriptions, for ranking
 * teachers in a trial search (docs/architecture/02, section 2: "نسبة تحويل
 * الحصص التجريبية", weight 10). The CRM knows it and binds its own; until
 * then nobody gets ahead.
 */
interface TrialConversionRates
{
    /**
     * @param  list<string>  $teacherIds
     * @return array<string, float> teacher id => rate between 0 and 1 (missing: no trials yet)
     */
    public function forTeachers(array $teacherIds): array;
}
