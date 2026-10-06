<?php

namespace Modules\Scheduling\Listeners;

use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Actions\FundSessions;

/** New minutes on an account (a paid block, a bonus): fund that account's waiting sessions, oldest first. */
final class FundSessionsFromNewCredit
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly FundSessions $fund,
    ) {}

    public function handle(CreditsGranted $event): void
    {
        if ($event->source === 'makeup') {
            return; // makeup minutes are for makeup sessions, booked on purpose
        }

        foreach ($this->enrollments->forCreditAccount($event->accountId) as $enrollment) {
            $this->fund->handle($enrollment->id);
        }
    }
}
