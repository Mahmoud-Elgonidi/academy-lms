<?php

namespace Modules\People\Actions;

use Modules\People\Models\GuardianStudent;

/** Links a guardian account to a student account in the current academy. */
final class LinkGuardian
{
    public function handle(string $guardianId, string $studentId, string $relationship = 'parent', bool $isPrimary = true): void
    {
        GuardianStudent::query()->firstOrCreate(
            ['guardian_id' => $guardianId, 'student_id' => $studentId],
            ['relationship' => $relationship, 'is_primary' => $isPrimary],
        );
    }
}
