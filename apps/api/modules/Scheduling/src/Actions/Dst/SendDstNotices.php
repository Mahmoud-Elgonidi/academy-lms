<?php

namespace Modules\Scheduling\Actions\Dst;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\Events\DstShiftUpcoming;
use Modules\Scheduling\Domain\Models\ShiftNotice;

/**
 * Tells the family their new hour 14 days and 2 days before a clock change
 * (docs/architecture/02, section 1), unless the academy kept their time. A
 * change found later than 14 days ahead is told once. A paused or ended
 * course's family is not told (its notices go when the planner runs again).
 */
final class SendDstNotices
{
    public const EARLY_DAYS = 14;

    public const LATE_DAYS = 2;

    /** @return int notices told */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $due = ShiftNotice::query()->where('decision', '!=', 'keep_student')->where('changes_at', '>', $now)
            ->whereNotIn('enrollment_id', DB::table('enrollments')->whereIn('status', ['paused', 'ended'])->select('id'))
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('notified_early_at')->where('changes_at', '<=', $now->addDays(self::EARLY_DAYS)))
                ->orWhere(fn ($q) => $q->whereNull('notified_late_at')->where('changes_at', '<=', $now->addDays(self::LATE_DAYS))))
            ->get();

        foreach ($due as $notice) {
            $late = $notice->changes_at->lessThanOrEqualTo($now->addDays(self::LATE_DAYS));
            $stage = $late && $notice->notified_early_at !== null ? 'late' : 'early';
            DB::transaction(function () use ($notice, $now, $late, $stage) {
                $notice->forceFill(array_filter([
                    'notified_early_at' => $notice->notified_early_at ?? $now,
                    'notified_late_at' => $late ? $now : null,
                ]))->save();
                DB::afterCommit(fn () => event(new DstShiftUpcoming($notice->academy_id, $notice->id, $stage)));
            });
        }

        return $due->count();
    }
}
