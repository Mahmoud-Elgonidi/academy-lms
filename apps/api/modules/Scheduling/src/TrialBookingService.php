<?php

namespace Modules\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;
use Modules\Scheduling\Contracts\TrialBooking;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\TrialSlots;
use Modules\Scheduling\Support\TrialLinks;

/**
 * A family's own free trial (Contracts\TrialBooking), booked the way Find a
 * teacher books one: a hold with the teacher (under the teacher's lock, the
 * minors check, free time checked again), then ConfirmBooking::trial, which
 * raises TrialLessonBooked. When the first teacher free then was just taken,
 * the next one is tried.
 */
final class TrialBookingService implements TrialBooking
{
    public function __construct(
        private readonly TrialSlots $slots,
        private readonly PlaceBookingHold $hold,
        private readonly ConfirmBooking $confirm,
        private readonly TrialLinks $links,
    ) {}

    public function slots(TrialSlotSearch $search): array
    {
        return $this->slots->slots($search);
    }

    public function book(TrialSlotSearch $search, CarbonImmutable $startsAt, string $studentId, string $actorId): string
    {
        $startsAt = $startsAt->utc();
        $taken = fn () => ValidationException::withMessages(['starts_at' => __('scheduling::trials.errors.taken')]);
        if ($startsAt->lessThan(CarbonImmutable::now()->addHours($search->noticeHours))) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::trials.errors.too_soon', ['hours' => $search->noticeHours])]);
        }
        $teachers = $this->slots->freeAt($search->onDayOf($startsAt), $startsAt);
        if ($teachers === []) {
            throw $taken();
        }
        foreach ($teachers as $teacherId) {
            try {
                $batch = $this->hold->handle($teacherId, $studentId, [$startsAt->getTimestamp()], $search->durationMin, $actorId);
            } catch (ValidationException) {
                continue;       // taken a moment ago, or not cleared for this student: the next one
            }

            return $this->confirm->trial($batch, $studentId, $search->subjectId, $actorId)->id;
        }

        throw $taken();
    }

    public function familyLinks(string $sessionId, string $locale): ?array
    {
        $session = Session::query()->find($sessionId);
        if ($session === null || $session->type !== SessionType::Trial || $session->status !== SessionStatus::Scheduled || ! $session->starts_at->isFuture()) {
            return null;
        }

        return $this->links->links($session, $locale);
    }
}
