<?php

namespace Modules\People\Actions\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Models\TeacherProfile;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Keeps a teacher from new lessons with students under 18 while a
 * safeguarding concern is looked into: nobody can book, move or hand them
 * such a lesson until it is lifted (ResumeTeacherForMinors). Lessons already
 * on the calendar stay for the academy to move. The teacher is not told by
 * the platform; the reason is for the academy's eyes. In the activity log.
 */
final class PauseTeacherForMinors
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $teacherId, string $reason, string $actorId): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('people::checks.errors.pause_reason')]);
        }
        DB::transaction(function () use ($teacherId, $reason, $actorId) {
            $profile = TeacherProfile::query()->whereKey($teacherId)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['teacher' => __('people::checks.errors.not_teacher')]);
            $profile->update(['minors_paused_at' => $profile->minors_paused_at ?? CarbonImmutable::now(), 'minors_paused_by' => $actorId, 'minors_pause_reason' => $reason]);
            $this->audit->record('people.minors_paused', 'user', $teacherId, [
                'teacher' => (string) DB::table('users')->where('id', $teacherId)->value('name'),
            ]);
        });
    }
}
