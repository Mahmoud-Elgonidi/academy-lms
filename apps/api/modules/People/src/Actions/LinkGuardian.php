<?php

namespace Modules\People\Actions;

use Modules\People\Actions\Consent\RequestConsent;
use Modules\People\Models\GuardianStudent;

/**
 * Links a guardian account to a student account in the current academy. A
 * new guardian of a child waiting for consent is asked for it (after commit).
 */
final class LinkGuardian
{
    public function __construct(private readonly RequestConsent $requestConsent) {}

    public function handle(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void
    {
        $link = GuardianStudent::query()->firstOrCreate(
            ['guardian_id' => $guardianId, 'student_id' => $studentId],
            ['relationship' => $relationship, 'is_primary' => $isPrimary],
        );
        if ($link->wasRecentlyCreated) {
            $this->requestConsent->handle($studentId, [$guardianId]);
        }
    }
}
