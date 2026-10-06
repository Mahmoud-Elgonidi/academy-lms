<?php

namespace Modules\Scheduling\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Dst\DecideDstChange;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Actions\ShiftSlotTimes;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\ShiftNotice;

/**
 * The DST planner on the demo's own dates: London and New York go back at
 * the end of October and Cairo the week after, so the planner finds the
 * students whose hour moves. For one Gulf student the academy already kept
 * their hour (their teacher is free an hour earlier), and for another it
 * confirmed the teacher's time; the rest wait on the clock changes screen.
 */
final class DemoDstSeeder extends Seeder
{
    public function run(PlanDstChanges $plan, SendDstNotices $send, DecideDstChange $decide, ShiftSlotTimes $shift): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');
        if (! $owner) {
            return;
        }
        $plan->handle();

        $gulf = ShiftNotice::query()->where('decision', 'pending')->where('cause_tz', 'Africa/Cairo')
            ->whereIn('other_tz', ['Asia/Riyadh', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Dubai'])->orderBy('changes_at')->get();
        $kept = null;
        foreach ($gulf as $notice) {
            $slot = RecurringSlot::query()->whereKey($notice->slot_id)->where('status', 'active')->first();
            if ($slot === null || $shift->plan([$slot], $notice->changes_at, $notice->reverts_at, -$notice->delta_min)['problems'] !== []) {
                continue;
            }
            try {
                $decide->handle($notice, 'keep_student', $owner);
                $kept = $notice;
                break;
            } catch (ValidationException) {
                continue;
            }
        }

        $other = ShiftNotice::query()->where('decision', 'pending')->where('cause_tz', 'Africa/Cairo')
            ->when($kept, fn ($q) => $q->where('student_id', '!=', $kept->student_id))->orderBy('changes_at')->first();
        if ($other !== null) {
            $decide->handle($other, 'keep_teacher', $owner);
        }

        $send->handle();
    }
}
