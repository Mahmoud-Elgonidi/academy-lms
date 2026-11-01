<?php

namespace Modules\Scheduling;

use Modules\Scheduling\Actions\BookAssessmentLesson;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Contracts\AssessmentLessons;
use Modules\Scheduling\Contracts\DTOs\AssessmentBooking;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;

/** Assessment lessons for the modules above (Contracts\AssessmentLessons), through Scheduling's own actions. */
final class AssessmentService implements AssessmentLessons
{
    public function __construct(
        private readonly BookAssessmentLesson $book,
        private readonly CancelSession $cancel,
    ) {}

    public function book(AssessmentBooking $booking): string
    {
        return $this->book->handle($booking)->id;
    }

    public function cancel(string $sessionId, ?string $actorId): void
    {
        $session = Session::query()->find($sessionId);
        if ($session === null || $session->type !== SessionType::Assessment || $session->status !== SessionStatus::Scheduled) {
            return;
        }
        $this->cancel->handle($session, 'academy', 'other', null, $actorId);
    }
}
