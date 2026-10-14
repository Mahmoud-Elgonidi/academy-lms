<?php

namespace Modules\Scheduling\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * A course's pause and end are days on the academy's calendar
 * (docs/architecture/02, section 3, "إيقاف الكورس وإنهاؤه"); lessons are
 * instants. This turns one into the other: a day starts at midnight on the
 * academy's clock (through LocalTimeResolver), so a lesson belongs to the
 * day it starts there. Every way a lesson of a course is made or booked
 * asks here whether the course has a lesson then.
 */
final class CourseDays
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly LocalTimeResolver $resolver,
    ) {}

    public function timezone(): string
    {
        return $this->tenants->current()?->defaultTimezone ?? 'UTC';
    }

    /** Today on the academy's calendar (a date at midnight). */
    public function today(?CarbonImmutable $now = null): CarbonImmutable
    {
        return CarbonImmutable::parse(($now ?? CarbonImmutable::now())->setTimezone($this->timezone())->toDateString());
    }

    /** The instant a day starts on the academy's clock. */
    public function startOf(CarbonInterface|string $day): CarbonImmutable
    {
        $date = $day instanceof CarbonInterface ? $day->toDateString() : substr($day, 0, 10);

        return $this->resolver->resolve($date, '00:00', $this->timezone())->utc;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} from the first day's midnight to the midnight after the last */
    public function span(CarbonInterface|string $first, CarbonInterface|string $last): array
    {
        $last = $last instanceof CarbonInterface ? $last->toDateString() : substr($last, 0, 10);

        return [$this->startOf($first), $this->startOf(CarbonImmutable::parse($last)->addDay())];
    }

    /**
     * Where a weekly time (a wall-clock time on its own clock) stops for a
     * course ending at $endsAt: the local date of its first lesson at or after
     * the end, excluded from its range, as ShiftSlotTimes ends a slot.
     */
    public function slotUntil(CarbonImmutable $endsAt, string $clock, string $timezone): string
    {
        $date = $endsAt->setTimezone($timezone)->toDateString();

        return $this->resolver->resolve($date, $clock, $timezone)->utc->greaterThanOrEqualTo($endsAt)
            ? $date : CarbonImmutable::parse($date)->addDay()->toDateString();
    }

    /** Why the course has no lesson starting at this instant ("paused", "ended"), or null when it may have one. */
    public function barred(?string $pausedFrom, ?string $pausedUntil, ?string $endsOn, CarbonImmutable $at, bool $ended = false): ?string
    {
        if ($ended || ($endsOn !== null && $at->greaterThanOrEqualTo($this->startOf($endsOn)))) {
            return 'ended';
        }
        if ($pausedFrom !== null && $pausedUntil !== null) {
            [$from, $until] = $this->span($pausedFrom, $pausedUntil);
            if ($at->greaterThanOrEqualTo($from) && $at->lessThan($until)) {
                return 'paused';
            }
        }

        return null;
    }

    public function barredFor(EnrollmentData $course, CarbonImmutable $at): ?string
    {
        return $this->barred($course->pausedFrom?->toDateString(), $course->pausedUntil?->toDateString(), $course->endsOn?->toDateString(), $at, $course->status->value === 'ended');
    }

    /** Refuses a lesson of the course at this instant when it is paused or over then. */
    public function assertOpen(EnrollmentData $course, CarbonImmutable $at): void
    {
        $why = $this->barredFor($course, $at);
        if ($why === 'ended') {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::courses.errors.ended_then')]);
        }
        if ($why === 'paused') {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::courses.errors.paused_then', [
                'until' => $course->pausedUntil?->locale(app()->getLocale())->translatedFormat('j F'),
            ])]);
        }
    }
}
