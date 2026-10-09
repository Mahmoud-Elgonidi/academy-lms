<?php

namespace Modules\People\Actions\Checks;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Models\TeacherProfile;
use Modules\SharedKernel\Audit\AuditTrail;

/** Lifts a teacher's pause from lessons with minors, with the academy's reason. In the activity log. */
final class ResumeTeacherForMinors
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $teacherId, string $reason, string $actorId): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('people::checks.errors.pause_reason')]);
        }
        DB::transaction(function () use ($teacherId, $reason) {
            $profile = TeacherProfile::query()->whereKey($teacherId)->lockForUpdate()->first();
            if ($profile?->minors_paused_at === null) {
                return;
            }
            $profile->update(['minors_paused_at' => null, 'minors_paused_by' => null, 'minors_pause_reason' => null]);
            $this->audit->record('people.minors_resumed', 'user', $teacherId, [
                'teacher' => (string) DB::table('users')->where('id', $teacherId)->value('name'), 'reason' => $reason,
            ]);
        });
    }
}
