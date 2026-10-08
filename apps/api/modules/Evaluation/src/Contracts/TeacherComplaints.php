<?php

namespace Modules\Evaluation\Contracts;

use Carbon\CarbonImmutable;

/**
 * How many complaints about each teacher the academy confirmed, for the
 * complaints KPI (docs/architecture/02, section 7: confirmed complaints per
 * 100 lessons). Support knows it and binds its own; until then nobody has any.
 */
interface TeacherComplaints
{
    /**
     * @param  list<string>  $teacherIds
     * @return array<string, int> teacher id => complaints made in [from, to) and confirmed (missing: none)
     */
    public function forTeachers(array $teacherIds, CarbonImmutable $from, CarbonImmutable $to): array;
}
