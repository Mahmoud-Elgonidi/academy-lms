<?php

namespace Modules\SharedKernel\Tenancy;

/**
 * Whom an academy teaches (docs/architecture/02, "أكاديميات الكبار والمهنيين"):
 * families (children with their guardians, and adults too: every academy
 * until the platform team says otherwise) or adults only (companies and
 * offices training professionals: no guardians, no children). Kept on the
 * academy (academies.audience), changed only through Platform's
 * SetAcademyAudience, read through AcademyAudience.
 */
enum Audience: string
{
    case Families = 'families';
    case Adults = 'adults';

    public const DEFAULT = self::Families;

    /** The kind written $value, else families (an unknown value never narrows an academy). */
    public static function of(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::DEFAULT;
    }
}
