<?php

namespace Modules\Scheduling\Actions\Dst;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\ShiftSlotTimes;
use Modules\Scheduling\Contracts\Events\DstShiftDecided;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\ShiftNotice;

/**
 * The academy's answer to a clock change (docs/architecture/02, section 1):
 * keep the teacher's time (the default; the family's hour moves and they
 * are told), or keep the student's: the teacher's time moves the other way
 * for as long as the two clocks differ (ShiftSlotTimes), if the teacher is
 * on shift and free at every new time. Keeping the student's time cannot be
 * taken back here: the lessons have moved.
 */
final class DecideDstChange
{
    public function __construct(
        private readonly ShiftSlotTimes $shift,
        private readonly PlanDstChanges $planner,
    ) {}

    public function handle(ShiftNotice $notice, string $decision, string $actorId): ShiftNotice
    {
        $l = 'scheduling::dst.errors.';
        if (! in_array($decision, ['keep_teacher', 'keep_student'], true)) {
            throw ValidationException::withMessages(['decision' => __($l.'decision')]);
        }
        if ($notice->decision === 'keep_student' || $notice->changes_at->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages(['decision' => __($l.'decided')]);
        }
        if ($notice->decision === $decision) {
            return $notice;
        }

        DB::transaction(function () use ($notice, $decision, $actorId, $l) {
            $notice->forceFill(['decision' => $decision, 'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now()])->save();
            if ($decision === 'keep_student') {
                $slot = RecurringSlot::query()->whereKey($notice->slot_id)->where('status', 'active')->first()
                    ?? throw ValidationException::withMessages(['decision' => __($l.'slot_gone')]);
                $this->shift->handle([$slot], $notice->changes_at, $notice->reverts_at, -$notice->delta_min, $actorId, 'dst');
                // The notices of this enrollment after it follow the new times (most of them no longer happen).
                $this->planner->handle(enrollmentIds: [$notice->enrollment_id]);
            }
            DB::afterCommit(fn () => event(new DstShiftDecided($notice->academy_id, $notice->id, $decision, $actorId)));
        });

        return $notice;
    }
}
