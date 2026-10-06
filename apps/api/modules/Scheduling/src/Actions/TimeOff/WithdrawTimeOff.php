<?php

namespace Modules\Scheduling\Actions\TimeOff;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\TimeOffDecided;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;

/**
 * Taking time off back: the teacher while it waits for an answer, a manager
 * also once granted, until it is over. Lessons already given to a substitute
 * stay with them; the teacher is told when it was not their own doing.
 */
final class WithdrawTimeOff
{
    public function handle(TeacherTimeOff $timeOff, string $actorId, bool $asManager = false): TeacherTimeOff
    {
        DB::transaction(function () use ($timeOff, $actorId, $asManager) {
            $current = TeacherTimeOff::query()->whereKey($timeOff->id)->lockForUpdate()->firstOrFail();
            $allowed = $asManager ? $current->status->canTransitionTo(TimeOffStatus::Withdrawn) : $current->status === TimeOffStatus::Pending;
            if (! $allowed || $current->ends_at->isPast()) {
                throw ValidationException::withMessages(['status' => __('scheduling::timeoff.errors.cannot_withdraw')]);
            }

            $timeOff->forceFill(['status' => TimeOffStatus::Withdrawn, 'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now()])->save();

            if ($actorId !== $timeOff->teacher_id) {
                DB::afterCommit(fn () => event(new TimeOffDecided($timeOff->academy_id, $timeOff->id, $timeOff->teacher_id, TimeOffStatus::Withdrawn->value, $actorId)));
            }
        });

        return $timeOff;
    }
}
