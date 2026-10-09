<?php

namespace Modules\Safeguarding\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\PeopleApi;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;

/**
 * While a concern about a teacher is looked into, a lead keeps them from new
 * lessons with students under 18 (People's pause: no booking, cover or move
 * to them), and lets them back when it is clear. Both go on the concern.
 */
final class PauseConcernTeacher
{
    public function __construct(private readonly PeopleApi $people) {}

    public function pause(string $concernId, string $reason, string $actorId): void
    {
        DB::transaction(function () use ($concernId, $reason, $actorId) {
            $concern = $this->concern($concernId);
            $this->people->pauseForMinors($concern->teacher_id, $concern->reference().' · '.trim($reason), $actorId);
            ConcernEntry::event($concern, 'teacher_paused', $actorId, ['teacher' => $concern->teacher_id]);
        });
    }

    public function resume(string $concernId, string $reason, string $actorId): void
    {
        DB::transaction(function () use ($concernId, $reason, $actorId) {
            $concern = $this->concern($concernId);
            $this->people->resumeForMinors($concern->teacher_id, $concern->reference().' · '.trim($reason), $actorId);
            ConcernEntry::event($concern, 'teacher_resumed', $actorId, ['teacher' => $concern->teacher_id]);
        });
    }

    private function concern(string $concernId): Concern
    {
        $concern = Concern::query()->whereKey($concernId)->lockForUpdate()->firstOrFail();
        if ($concern->teacher_id === null) {
            throw ValidationException::withMessages(['teacher' => __('safeguarding::concerns.errors.no_teacher')]);
        }

        return $concern;
    }
}
