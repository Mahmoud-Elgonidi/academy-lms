<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\BookingHold;
use Modules\Scheduling\Queries\AvailabilityQuery;

/**
 * Holds the chosen weekly times with a teacher for ten minutes while the
 * coordinator finishes the enrolment (docs/architecture/02, section 2, "منع
 * الحجز المزدوج"). Other coordinators see the time as taken. A new hold by
 * the same coordinator replaces their previous one.
 */
final class PlaceBookingHold
{
    public function __construct(private readonly AvailabilityQuery $availability) {}

    /**
     * @param  list<int>  $occurrences  UTC start of every session to hold
     * @return string the hold batch id
     */
    public function handle(string $teacherId, ?string $studentId, array $occurrences, int $durationMin, string $coordinatorId): string
    {
        if ($occurrences === [] || $durationMin <= 0) {
            throw ValidationException::withMessages(['hold' => __('scheduling::find.errors.nothing_chosen')]);
        }
        sort($occurrences);

        return DB::transaction(function () use ($teacherId, $studentId, $occurrences, $durationMin, $coordinatorId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['book:'.$teacherId]);
            BookingHold::query()->where('created_by', $coordinatorId)->delete();

            $from = CarbonImmutable::createFromTimestamp($occurrences[0], 'UTC');
            $to = CarbonImmutable::createFromTimestamp(end($occurrences) + $durationMin * 60, 'UTC');
            $free = $this->availability->forTeachers([$teacherId], $from, $to, $coordinatorId)[$teacherId]->free ?? null;

            $taken = array_filter($occurrences, fn (int $ts) => $free === null || ! $free->contains($ts, $ts + $durationMin * 60));
            if ($taken !== []) {
                throw ValidationException::withMessages(['hold' => __('scheduling::find.errors.taken', [
                    'dates' => implode('، ', array_map(fn (int $ts) => CarbonImmutable::createFromTimestamp($ts, 'UTC')->toDateString(), $taken)),
                ])]);
            }

            $batch = (string) Str::uuid();
            $expires = CarbonImmutable::now()->addMinutes(BookingHold::MINUTES);
            foreach ($occurrences as $ts) {
                BookingHold::query()->create([
                    'batch_id' => $batch,
                    'teacher_id' => $teacherId,
                    'student_id' => $studentId,
                    'starts_at' => CarbonImmutable::createFromTimestamp($ts, 'UTC'),
                    'ends_at' => CarbonImmutable::createFromTimestamp($ts + $durationMin * 60, 'UTC'),
                    'expires_at' => $expires,
                    'created_by' => $coordinatorId,
                ]);
            }

            return $batch;
        });
    }
}
