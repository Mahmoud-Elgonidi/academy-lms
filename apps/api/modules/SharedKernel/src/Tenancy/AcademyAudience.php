<?php

namespace Modules\SharedKernel\Tenancy;

/**
 * Whom the academy of this request or job teaches: families, or adults only.
 * The academy's own value comes with its TenantRef (Platform reads it from
 * academies once, when the tenant is resolved), so current() costs nothing;
 * with no academy it is families. Words follow it on their own (the
 * translator reads a module's lang/{locale}/adults/{file}.php first for an
 * adults academy); a module asks adultsOnly() to hide what has no place
 * there (guardians, children, the kids' looks).
 */
interface AcademyAudience
{
    /** The current academy's, or families when no academy is set. */
    public function current(): Audience;

    /** True when the current academy teaches adults only. */
    public function adultsOnly(): bool;

    /** Another academy's, by id (a sweeper, a platform screen); families for an unknown one. */
    public function forAcademy(string $academyId): Audience;
}
