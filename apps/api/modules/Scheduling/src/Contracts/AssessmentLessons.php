<?php

namespace Modules\Scheduling\Contracts;

use Modules\Scheduling\Contracts\DTOs\AssessmentBooking;

/**
 * Assessment lessons (an exam another module holds: the Quran's), booked
 * and cancelled here so every rule of a lesson stays Scheduling's: a course
 * that is running and not paused or over on that day (CourseDays), an
 * examiner People clears for a student under 18 (MinorsGuard), nobody
 * booked twice at once (the database), free of the course's minutes.
 */
interface AssessmentLessons
{
    /** Books the lesson; returns its id. Refuses with a ValidationException saying why. */
    public function book(AssessmentBooking $booking): string;

    /** Cancels it for the academy (no credit moves); a lesson already over or cancelled is left. */
    public function cancel(string $sessionId, ?string $actorId): void;
}
