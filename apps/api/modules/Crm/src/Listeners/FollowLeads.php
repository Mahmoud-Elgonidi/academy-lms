<?php

namespace Modules\Crm\Listeners;

use Carbon\CarbonImmutable;
use Modules\Crm\Actions\MarkLeadWon;
use Modules\Crm\Actions\TrackTrial;
use Modules\Enrollment\Contracts\Events\EnrollmentCreated;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;

/** The CRM follows its leads through other modules' events (docs/architecture/04: "CRM بيسمع TrialCompleted"). */
final class FollowLeads
{
    public function __construct(private readonly TrackTrial $trials, private readonly MarkLeadWon $won) {}

    public function trialBooked(TrialLessonBooked $event): void
    {
        $this->trials->booked($event->sessionId, $event->studentId, $event->teacherId, CarbonImmutable::parse($event->startsAtUtc), $event->actorId);
    }

    public function rescheduled(SessionRescheduled $event): void
    {
        $this->trials->moved($event->sessionId, $event->actorId);
    }

    public function cancelled(SessionCancelled $event): void
    {
        $this->trials->cancelled($event->sessionId, $event->reason, $event->actorId);
    }

    public function finalized(SessionFinalized $event): void
    {
        $this->trials->finished($event->sessionId, $event->outcome);
    }

    public function enrolled(EnrollmentCreated $event): void
    {
        $this->won->handle($event->studentId, $event->enrollmentId, $event->teacherId);
    }
}
