<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Models\GuardianStudent;

/** How a guardian is related to a student, whether they are the main one (one per student), and whether they get the lesson reports. */
final class UpdateGuardianLink
{
    public const RELATIONSHIPS = ['parent', 'father', 'mother', 'sibling', 'grandparent', 'other'];

    public function handle(string $guardianId, string $studentId, string $relationship, bool $isPrimary, bool $receivesReports): void
    {
        if (! in_array($relationship, self::RELATIONSHIPS, true)) {
            throw ValidationException::withMessages(['relationship' => __('people::directory.errors.relationship')]);
        }
        DB::transaction(function () use ($guardianId, $studentId, $relationship, $isPrimary, $receivesReports) {
            if ($isPrimary) {
                GuardianStudent::query()->where('student_id', $studentId)->where('guardian_id', '!=', $guardianId)->update(['is_primary' => false]);
            }
            GuardianStudent::query()->where('student_id', $studentId)->where('guardian_id', $guardianId)
                ->update(['relationship' => $relationship, 'is_primary' => $isPrimary, 'receives_reports' => $receivesReports]);
        });
    }
}
