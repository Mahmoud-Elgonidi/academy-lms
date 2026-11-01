<?php

namespace Modules\Scheduling\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\DTOs\TrialSlot;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;

/**
 * Free trial lessons a family books itself (the family's journey,
 * docs/architecture/02 section 27), for the modules above (the CRM): the
 * starts on offer across teachers who take trials, teach the subject and the
 * age, are of the gender asked for and, for a child, are cleared for minors
 * (MinorsGuard), less their lessons, time off, holidays, daily caps and
 * others' holds (AvailabilityQuery); and the booking itself, through
 * PlaceBookingHold and ConfirmBooking::trial like Find a teacher.
 */
interface TrialBooking
{
    /** At least this long before a trial starts, a family may book it, unless the academy chose otherwise (TrialSlotSearch::noticeHours). */
    public const MIN_NOTICE_HOURS = 3;

    /** Until this long before it starts, a family may move its trial. */
    public const MOVE_UNTIL_HOURS = 6;

    /** A family moves its trial at most this many times; then it cancels and asks again. */
    public const MAX_MOVES = 2;

    /** @return list<TrialSlot> by start, on a half-hour grid of the family's clock */
    public function slots(TrialSlotSearch $search): array;

    /**
     * Books the trial for the student at a start the search offers, with the
     * least busy of the teachers free then; returns the lesson's id.
     *
     * @throws ValidationException on "starts_at" when nobody is free then any more (or it is too soon)
     */
    public function book(TrialSlotSearch $search, CarbonImmutable $startsAt, string $studentId, string $actorId): string;

    /**
     * The family's own links for a trial lesson (their portal's pages, signed,
     * no sign-in): change the time, and "we cannot come". Null for a lesson
     * that is not a coming trial.
     *
     * @return array{change: string, cancel: string}|null
     */
    public function familyLinks(string $sessionId, string $locale): ?array;
}
