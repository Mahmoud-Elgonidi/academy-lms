<?php

namespace Modules\Scheduling\Actions\TimeOff;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\TimeOffDecided;
use Modules\Scheduling\Domain\Enums\TimeOffStatus;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;

/**
 * The academy's answer to a leave request. Approved, the hours leave the
 * teacher's availability at once, and the lessons in them wait for a
 * substitute or a makeup on the cover screen (nothing is moved by itself
 * until an hour before each lesson: CancelUncoveredLessons).
 */
final class DecideTimeOff
{
    public function handle(TeacherTimeOff $timeOff, bool $approve, ?string $note, string $actorId): TeacherTimeOff
    {
        $next = $approve ? TimeOffStatus::Approved : TimeOffStatus::Rejected;

        DB::transaction(function () use ($timeOff, $next, $note, $actorId) {
            $current = TeacherTimeOff::query()->whereKey($timeOff->id)->lockForUpdate()->firstOrFail();
            if (! $current->status->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => __('scheduling::timeoff.errors.decided')]);
            }
            if ($next === TimeOffStatus::Approved && $current->ends_at->isPast()) {
                throw ValidationException::withMessages(['status' => __('scheduling::timeoff.errors.over')]);
            }

            $timeOff->forceFill([
                'status' => $next,
                'decided_by' => $actorId,
                'decided_at' => CarbonImmutable::now(),
                'decision_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ])->save();

            DB::afterCommit(fn () => event(new TimeOffDecided($timeOff->academy_id, $timeOff->id, $timeOff->teacher_id, $next->value, $actorId)));
        });

        return $timeOff;
    }
}
