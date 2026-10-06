<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\BookingHold;
use Modules\Scheduling\Domain\Models\Session;

/**
 * Turns a hold into sessions, all or nothing: a trial (the first time, free)
 * or the weekly series over the horizon. Until Enrollment and Billing land,
 * series sessions are booked as unfunded and the live board shows them so.
 * The exclusion constraint is the last word on double-booking.
 */
final class ConfirmBooking
{
    public function __construct(private readonly ScheduleSession $schedule) {}

    /** @return list<Session> */
    public function handle(string $batchId, string $studentId, string $subjectId, string $kind, string $coordinatorId): array
    {
        $holds = BookingHold::query()->where('batch_id', $batchId)->where('created_by', $coordinatorId)->orderBy('starts_at')->get();
        if ($holds->isEmpty() || $holds->first()->expires_at->isPast()) {
            throw ValidationException::withMessages(['hold' => __('scheduling::find.errors.hold_expired')]);
        }
        if (! in_array($kind, ['trial', 'series'], true)) {
            throw ValidationException::withMessages(['kind' => __('scheduling::find.errors.kind')]);
        }

        $teacherTz = DB::table('users')->where('id', $holds->first()->teacher_id)->value('timezone') ?? 'UTC';
        $toBook = $kind === 'trial' ? $holds->take(1) : $holds;

        return DB::transaction(function () use ($toBook, $batchId, $studentId, $subjectId, $kind, $coordinatorId, $teacherTz) {
            $sessions = [];
            foreach ($toBook as $hold) {
                $sessions[] = $this->schedule->handle(new NewSession(
                    teacherId: $hold->teacher_id,
                    studentId: $studentId,
                    subjectId: $subjectId,
                    startsAt: $hold->starts_at,
                    durationMin: (int) $hold->starts_at->diffInMinutes($hold->ends_at),
                    anchorTimezone: $teacherTz,
                    type: $kind === 'trial' ? 'trial' : 'regular',
                    funding: $kind === 'trial' ? 'free' : 'unfunded',
                ), $coordinatorId);
            }

            BookingHold::query()->where('batch_id', $batchId)->delete();

            return $sessions;
        });
    }

    public static function expiresIn(string $batchId): ?int
    {
        $expires = BookingHold::query()->where('batch_id', $batchId)->value('expires_at');

        return $expires ? max(0, CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($expires), false)) : null;
    }
}
