<?php

namespace Modules\Billing\Contracts\DTOs;

/**
 * What ending a course does to its block invoice waiting to be paid
 * (docs/architecture/02, section 3, "إيقاف الكورس وإنهاؤه"): nothing is
 * waiting (none); it is voided (voided), or, a family invoice, voided and
 * the family's other courses issued again on a new one (reissued); it
 * stays because money may be on its way (kept, and why: paid, proof,
 * checkout); or it is a shared package's, which goes on for the student's
 * other courses (shared). A waiting extra-lessons invoice for the course
 * goes on the same terms, alongside.
 */
final readonly class EndedCourseBilling
{
    public const NONE = 'none';

    public const VOIDED = 'voided';

    public const REISSUED = 'reissued';

    public const KEPT = 'kept';

    public const SHARED = 'shared';

    public function __construct(
        public string $outcome,
        public ?string $invoiceId = null,
        public ?string $invoiceNumber = null,
        /** Why it stays (kept): something paid on it, a proof waiting, an online payment under way. */
        public ?string $why = null,
        /** The new invoice for the family's other courses (reissued). */
        public ?string $reissuedNumber = null,
        /** The course's waiting extra-lessons invoice that goes too (voided like a block), if any. */
        public ?string $extraNumber = null,
    ) {}
}
