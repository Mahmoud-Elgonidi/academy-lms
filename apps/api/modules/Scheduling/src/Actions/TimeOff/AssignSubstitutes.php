<?php

namespace Modules\Scheduling\Actions\TimeOff;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\SubstituteTeacher;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;
use Modules\Scheduling\Queries\SubstituteCandidates;

/**
 * "Cover them all": each lesson of the leave still with its teacher goes to
 * the first candidate that is still free once the earlier ones are placed;
 * a lesson nobody can take is left for the academy to decide.
 */
final class AssignSubstitutes
{
    public function __construct(
        private readonly SubstituteCandidates $candidates,
        private readonly SubstituteTeacher $substitute,
    ) {}

    /** @return array{covered: int, left: int} */
    public function handle(TeacherTimeOff $timeOff, string $actorId): array
    {
        $lessons = Session::query()->where('teacher_id', $timeOff->teacher_id)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>', CarbonImmutable::now())->where('starts_at', '<', $timeOff->ends_at)->where('ends_at', '>', $timeOff->starts_at)
            ->orderBy('starts_at')->get();

        $covered = 0;
        foreach ($lessons as $lesson) {
            foreach ($this->candidates->forSessions([$lesson], $actorId)[$lesson->id] ?? [] as $candidate) {
                try {
                    $this->substitute->handle($lesson, $candidate['id'], $actorId);
                    $covered++;
                    break;
                } catch (ValidationException) {
                    continue;
                }
            }
        }

        return ['covered' => $covered, 'left' => $lessons->count() - $covered];
    }
}
