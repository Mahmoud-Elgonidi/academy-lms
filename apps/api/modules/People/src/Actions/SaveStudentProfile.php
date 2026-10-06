<?php

namespace Modules\People\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Models\StudentProfile;

/** A student's birth date, and the look of their area when the academy wants other than their age gives. */
final class SaveStudentProfile
{
    public function handle(string $studentId, ?CarbonImmutable $birthDate, ?LearnerStage $stage): StudentProfile
    {
        if ($birthDate !== null && ($birthDate->isFuture() || $birthDate->lessThan(CarbonImmutable::now()->subYears(100)))) {
            throw ValidationException::withMessages(['birth_date' => __('people::family.errors.birth_date')]);
        }

        return StudentProfile::query()->updateOrCreate(['user_id' => $studentId], [
            'birth_date' => $birthDate?->toDateString(),
            'stage' => $stage?->value,
        ]);
    }
}
