<?php

namespace Modules\Scheduling\Actions\Dst;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The DST planner's daily look ahead (docs/architecture/02, section 1): each
 * weekly lesson of the next 45 days next to the same lesson a week before,
 * on the student's clock. Where the hour moved while the teacher's did not
 * (the difference between the two clocks changed), one notice: before and after, by how much,
 * whose clock changed, and until when the difference holds (from both
 * zones' transitions). Lessons are paired by time, not by weekly time, so a
 * weekly time split by a move or a new teacher is still followed. Run again,
 * it refreshes what it finds and drops what no longer happens (unless the
 * academy kept the student's time there: that one is a record).
 */
final class PlanDstChanges
{
    public const DAYS_AHEAD = 45;

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  list<string>|null  $enrollmentIds  null: every enrollment with a live weekly time
     * @return int new notices
     */
    public function handle(?CarbonImmutable $now = null, ?array $enrollmentIds = null): int
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $until = $now->addDays(self::DAYS_AHEAD);
        $fallback = $this->tenants->current()?->defaultTimezone ?? 'UTC';

        $slots = RecurringSlot::query()->where('status', 'active')
            ->when($enrollmentIds !== null, fn ($q) => $q->whereIn('enrollment_id', $enrollmentIds))
            ->get()->keyBy('id');
        $zones = DB::table('users')->whereIn('id', $slots->pluck('student_id')->unique())->pluck('timezone', 'id');
        $lessons = Session::query()->whereIn('enrollment_id', $slots->pluck('enrollment_id')->unique())
            ->whereNotNull('slot_id')->where('is_detached', false)
            ->where('starts_at', '>=', $now->subDays(15))->where('starts_at', '<', $until)
            ->orderBy('starts_at')->get(['id', 'enrollment_id', 'slot_id', 'starts_at', 'status'])
            ->groupBy('enrollment_id');

        $found = [];
        $new = 0;
        foreach ($lessons as $ofEnrollment) {
            foreach ($ofEnrollment as $lesson) {
                $slot = $slots[$lesson->slot_id] ?? null;
                $other = $slot !== null ? ($zones[$slot->student_id] ?? $fallback) : null;
                if ($slot === null || $other === $slot->anchor_tz || $lesson->starts_at->lessThanOrEqualTo($now)) {
                    continue;
                }
                $previous = self::weekBefore($ofEnrollment, $lesson);
                if ($previous === null) {
                    continue;
                }
                $before = $previous->starts_at->setTimezone($other);
                $after = $lesson->starts_at->setTimezone($other);
                if (self::minuteOfWeek($before) === self::minuteOfWeek($after)
                    || self::minuteOfWeek($previous->starts_at->setTimezone($slot->anchor_tz)) !== self::minuteOfWeek($lesson->starts_at->setTimezone($slot->anchor_tz))) {
                    continue;   // the same hour for the student, or moved on the teacher's clock: not the clocks' doing
                }
                [$notice, $created] = $this->record($slot, $other, $before, $after, $lesson->starts_at);
                $found[] = $notice->id;
                $new += $created ? 1 : 0;
            }
        }

        ShiftNotice::query()->where('changes_at', '>', $now)->where('changes_at', '<', $until)->where('decision', '!=', 'keep_student')
            ->when($enrollmentIds !== null, fn ($q) => $q->whereIn('enrollment_id', $enrollmentIds))
            ->whereNotIn('id', $found)->delete();

        return $new;
    }

    /** @return array{0: ShiftNotice, 1: bool} the notice, and whether it is new */
    private function record(RecurringSlot $slot, string $other, CarbonImmutable $before, CarbonImmutable $after, CarbonImmutable $changesAt): array
    {
        $delta = self::minuteOfWeek($after) - self::minuteOfWeek($before);
        $delta = (($delta + 5040) % 10080 + 10080) % 10080 - 5040;     // the short way round the week
        $reverts = self::revertsAt($slot->anchor_tz, $other, $changesAt);
        $lessons = Session::query()->where('slot_id', $slot->id)->where('status', 'scheduled')->where('is_detached', false)
            ->where('starts_at', '>=', $changesAt)->when($reverts, fn ($q) => $q->where('starts_at', '<', $reverts))->count();

        $notice = ShiftNotice::query()->firstOrNew(['enrollment_id' => $slot->enrollment_id, 'changes_at' => $changesAt]);
        $created = ! $notice->exists;
        $notice->fill([
            'slot_id' => $slot->id,
            'teacher_id' => $slot->teacher_id,
            'student_id' => $slot->student_id,
            'anchor_tz' => $slot->anchor_tz,
            'other_tz' => $other,
            'cause_tz' => self::offset($slot->anchor_tz, $before) !== self::offset($slot->anchor_tz, $after) ? $slot->anchor_tz : $other,
            'reverts_at' => $reverts,
            'before_weekday' => $before->dayOfWeekIso,
            'before_time' => $before->format('H:i'),
            'after_weekday' => $after->dayOfWeekIso,
            'after_time' => $after->format('H:i'),
            'delta_min' => $delta,
            'lessons' => $lessons,
        ])->save();

        return [$notice, $created];
    }

    /**
     * The same weekly lesson a week before (two weeks, when a holiday left
     * that week out): the one within three hours of it, on the same weekly
     * time if there is one.
     *
     * @param  Collection<int, Session>  $lessons
     */
    private static function weekBefore(Collection $lessons, Session $lesson): ?Session
    {
        foreach ([7, 14] as $days) {
            $target = $lesson->starts_at->subDays($days)->getTimestamp();
            $near = $lessons->filter(fn (Session $s) => abs($s->starts_at->getTimestamp() - $target) <= 3 * 3600);
            if ($near->isNotEmpty()) {
                return $near->firstWhere('slot_id', $lesson->slot_id)
                    ?? $near->sortBy(fn (Session $s) => abs($s->starts_at->getTimestamp() - $target))->first();
            }
        }

        return null;
    }

    /** When the difference between the two clocks changes again after $at, within a year (null: not within a year). */
    public static function revertsAt(string $anchor, string $other, CarbonImmutable $at): ?CarbonImmutable
    {
        $start = $at->getTimestamp();
        $base = self::difference($anchor, $other, $at);

        $moments = [];
        foreach ([$anchor, $other] as $zone) {
            foreach ((new DateTimeZone($zone))->getTransitions($start + 1, $start + 366 * 86400) ?: [] as $i => $t) {
                if ($i > 0) {       // the first entry is the state at $start, not a transition
                    $moments[] = $t['ts'];
                }
            }
        }
        sort($moments);
        foreach ($moments as $ts) {
            if (self::difference($anchor, $other, CarbonImmutable::createFromTimestampUTC($ts)) !== $base) {
                return CarbonImmutable::createFromTimestampUTC($ts);
            }
        }

        return null;
    }

    /** Seconds the other clock is ahead of the anchor's at an instant. */
    private static function difference(string $anchor, string $other, CarbonImmutable $at): int
    {
        return self::offset($other, $at) - self::offset($anchor, $at);
    }

    private static function minuteOfWeek(CarbonImmutable $local): int
    {
        return ($local->dayOfWeekIso - 1) * 1440 + $local->hour * 60 + $local->minute;
    }

    private static function offset(string $zone, CarbonImmutable $at): int
    {
        return (new DateTimeZone($zone))->getOffset(new DateTimeImmutable('@'.$at->getTimestamp()));
    }
}
