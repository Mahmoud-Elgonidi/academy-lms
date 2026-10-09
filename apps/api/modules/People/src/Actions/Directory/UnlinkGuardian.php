<?php

namespace Modules\People\Actions\Directory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Models\GuardianStudent;

/**
 * A guardian no longer looks after a student. A child with no sign-in of
 * their own keeps at least one guardian (someone has to sign in for them);
 * when the main guardian goes, the one linked longest takes their place.
 */
final class UnlinkGuardian
{
    public function handle(string $guardianId, string $studentId): void
    {
        DB::transaction(function () use ($guardianId, $studentId) {
            $links = GuardianStudent::query()->where('student_id', $studentId)->lockForUpdate()->orderBy('created_at')->get();
            $link = $links->firstWhere('guardian_id', $guardianId);
            if ($link === null) {
                return;
            }
            $managed = (bool) DB::table('users')->where('id', $studentId)->value('is_managed_minor');
            if ($managed && $links->count() === 1) {
                throw ValidationException::withMessages(['guardian' => __('people::directory.errors.last_guardian')]);
            }

            GuardianStudent::query()->where('student_id', $studentId)->where('guardian_id', $guardianId)->delete();
            $rest = $links->reject(fn (GuardianStudent $l) => $l->guardian_id === $guardianId);
            if ($link->is_primary && $rest->isNotEmpty() && ! $rest->contains('is_primary', true)) {
                GuardianStudent::query()->where('student_id', $studentId)->where('guardian_id', $rest->first()->guardian_id)->update(['is_primary' => true]);
            }
        });
    }
}
