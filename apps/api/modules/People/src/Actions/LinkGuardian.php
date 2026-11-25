<?php

namespace Modules\People\Actions;

use Illuminate\Validation\ValidationException;
use Modules\People\Actions\Consent\RequestConsent;
use Modules\People\Models\GuardianStudent;
use Modules\SharedKernel\Tenancy\AcademyAudience;

/**
 * Links a guardian account to a student account in the current academy. A
 * new guardian of a child waiting for consent is asked for it (after commit),
 * unless the link is for a free trial: then it is asked when first needed.
 * An academy of adults only links nobody (docs/architecture/02, section 29).
 */
final class LinkGuardian
{
    public function __construct(private readonly RequestConsent $requestConsent, private readonly AcademyAudience $audience) {}

    public function handle(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true, bool $askConsent = true): void
    {
        if ($this->audience->adultsOnly()) {
            throw ValidationException::withMessages(['guardian' => __('people::directory.errors.adults_no_guardians')]);
        }
        $link = GuardianStudent::query()->firstOrCreate(
            ['guardian_id' => $guardianId, 'student_id' => $studentId],
            ['relationship' => $relationship, 'is_primary' => $isPrimary],
        );
        if ($link->wasRecentlyCreated && $askConsent) {
            $this->requestConsent->handle($studentId, [$guardianId]);
        }
    }
}
