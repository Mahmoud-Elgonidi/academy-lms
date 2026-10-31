<?php

namespace Modules\Quran\Support;

use Modules\Catalog\Contracts\CatalogApi;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * Whether the Quran is the academy's now (docs/architecture/02, section 26):
 * its SaaS plan has the Quran's area (an academy on no plan has everything).
 * Without it the module's screens and routes are closed and nothing new is
 * written, and every contract it binds answers as if it were not there; what
 * it holds stays as it is, for when the area comes back.
 */
final class QuranArea
{
    public function __construct(
        private readonly AcademyPlan $plan,
        private readonly CatalogApi $catalog,
    ) {}

    public function on(): bool
    {
        return $this->plan->has(Feature::Quran);
    }

    /** Whether a subject is of the Quran kind (Catalog's subjects.kind). */
    public function isQuranSubject(string $subjectId): bool
    {
        return ($this->catalog->subjectKind($subjectId)['kind'] ?? null) === 'quran';
    }
}
