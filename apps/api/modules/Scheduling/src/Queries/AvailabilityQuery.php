<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Availability\IntervalSet;
use Modules\Scheduling\Domain\Availability\TeacherAvailability;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Free time per teacher over a horizon: the approved schedule in force each
 * day, unfolded day by day on the teacher's clock (so summer time is right),
 * less approved time off and the academy's holidays (no shift then), minus
 * booked sessions and other coordinators' unexpired holds.
 *
 * Computed on demand; the Redis week bitsets of docs/architecture/02,
 * section 2 replace this when teacher counts call for it.
 */
final class AvailabilityQuery
{
    public function __construct(private readonly LocalTimeResolver $resolver) {}

    /**
     * @param  list<string>  $teacherIds
     * @return array<string, TeacherAvailability>
     */
    public function forTeachers(array $teacherIds, CarbonImmutable $from, CarbonImmutable $to, ?string $ignoreHoldsOf = null): array
    {
        if ($teacherIds === []) {
            return [];
        }

        $schedules = DB::table('teacher_shift_schedules')
            ->whereIn('teacher_id', $teacherIds)
            ->where('status', 'active')
            ->whereRaw('effective && daterange(?::date, ?::date)', [$from->subDays(2)->toDateString(), $to->addDays(2)->toDateString()])
            ->get(['id', 'teacher_id', 'timezone', 'effective']);
        $rules = DB::table('teacher_shift_rules')->whereIn('schedule_id', $schedules->pluck('id'))->get()->groupBy('schedule_id');

        $busy = DB::table('session_participants')
            ->whereIn('user_id', $teacherIds)
            ->where('is_active', true)
            ->whereRaw('block_period && tstzrange(?, ?)', [$from, $to])
            ->selectRaw('user_id, extract(epoch from lower(block_period))::bigint as s, extract(epoch from upper(block_period))::bigint as e')
            ->get()
            ->groupBy('user_id');

        $holds = DB::table('booking_holds')
            ->whereIn('teacher_id', $teacherIds)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->when($ignoreHoldsOf, fn ($q) => $q->where('created_by', '!=', $ignoreHoldsOf))
            ->where('ends_at', '>', $from)->where('starts_at', '<', $to)
            ->selectRaw('teacher_id, extract(epoch from starts_at)::bigint as s, extract(epoch from ends_at)::bigint as e')
            ->get()
            ->groupBy('teacher_id');

        $off = DB::table('teacher_time_off')
            ->whereIn('teacher_id', $teacherIds)
            ->where('status', 'approved')
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->selectRaw('teacher_id, extract(epoch from starts_at)::bigint as s, extract(epoch from ends_at)::bigint as e')
            ->get()
            ->groupBy('teacher_id');
        $closed = DB::table('academy_holidays')
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->selectRaw('extract(epoch from starts_at)::bigint as s, extract(epoch from ends_at)::bigint as e')
            ->get()->map(fn ($h) => [(int) $h->s, (int) $h->e])->all();

        $result = [];
        foreach ($schedules->groupBy('teacher_id') as $teacherId => $versions) {
            $timezone = $versions->first()->timezone;
            $shiftIntervals = [];

            $day = $from->setTimezone($timezone)->startOfDay()->subDay();
            $last = $to->setTimezone($timezone)->startOfDay()->addDay();
            for (; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
                $version = $versions->first(fn (object $v) => self::inRange($v->effective, $day->toDateString()));
                foreach ($version ? $rules->get($version->id, collect()) : [] as $rule) {
                    if ((int) $rule->weekday !== $day->dayOfWeekIso) {
                        continue;
                    }
                    $start = $this->resolver->resolve($day->toDateString(), ShiftBlock::clock(ShiftBlock::minutes($rule->local_start)), $version->timezone)->utc->getTimestamp();
                    $shiftIntervals[] = [$start, $start + $rule->duration_min * 60];
                }
            }

            $shifts = (new IntervalSet($shiftIntervals))
                ->minus(new IntervalSet([[PHP_INT_MIN, $from->getTimestamp()], [$to->getTimestamp(), PHP_INT_MAX]]))
                ->minus(new IntervalSet(array_merge($closed, $off->get($teacherId, collect())->map(fn ($o) => [(int) $o->s, (int) $o->e])->all())));
            $taken = new IntervalSet(array_merge(
                $busy->get($teacherId, collect())->map(fn ($b) => [(int) $b->s, (int) $b->e])->all(),
                $holds->get($teacherId, collect())->map(fn ($h) => [(int) $h->s, (int) $h->e])->all(),
            ));

            $result[$teacherId] = new TeacherAvailability($teacherId, $timezone, $shifts, $shifts->minus($taken));
        }

        return $result;
    }

    /** Is the date inside a Postgres daterange literal such as "[2026-10-06,2026-10-24)" or "[2026-10-06,)"? */
    public static function inRange(string $range, string $date): bool
    {
        [$lower, $upper] = array_map(fn (string $p) => trim($p, '[]() '), explode(',', $range));

        return ($lower === '' || $date >= $lower) && ($upper === '' || $date < $upper);
    }
}
