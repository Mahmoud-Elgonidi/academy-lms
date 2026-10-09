<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Domain\Models\BookingHold;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Books the free trial from a hold: the first held time, then the hold is
 * released, and everyone hears of it (TrialLessonBooked). A weekly series
 * is an enrolment (EnrollStudent): plan, invoice, weekly times and sessions
 * together.
 */
final class ConfirmBooking
{
    public function __construct(
        private readonly ScheduleSession $schedule,
        private readonly TakeHeldBatch $batch,
        private readonly MinorsGuard $minors,
    ) {}

    public function trial(string $batchId, string $studentId, string $subjectId, string $coordinatorId): Session
    {
        $hold = $this->batch->handle($batchId, $coordinatorId)->first();
        $this->minors->assert($hold->teacher_id, $studentId);
        $teacherTz = DB::table('users')->where('id', $hold->teacher_id)->value('timezone') ?? 'UTC';

        return DB::transaction(function () use ($hold, $batchId, $studentId, $subjectId, $coordinatorId, $teacherTz) {
            $session = $this->schedule->handle(new NewSession(
                teacherId: $hold->teacher_id,
                studentId: $studentId,
                subjectId: $subjectId,
                startsAt: $hold->starts_at,
                durationMin: (int) $hold->starts_at->diffInMinutes($hold->ends_at),
                anchorTimezone: $teacherTz,
                type: 'trial',
                funding: 'free',
            ), $coordinatorId);

            BookingHold::query()->where('batch_id', $batchId)->delete();
            DB::afterCommit(fn () => event(new TrialLessonBooked(
                $session->academy_id, $session->id, $studentId, $session->teacher_id, $subjectId, $session->starts_at->utc()->toIso8601ZuluString(), $coordinatorId,
            )));

            return $session;
        });
    }

    public static function expiresIn(string $batchId): ?int
    {
        $expires = BookingHold::query()->where('batch_id', $batchId)->value('expires_at');

        return $expires ? max(0, CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($expires), false)) : null;
    }
}
