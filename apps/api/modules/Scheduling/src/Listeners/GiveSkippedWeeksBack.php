<?php

namespace Modules\Scheduling\Listeners;

use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\Events\EnrollmentActivated;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Domain\Models\CourseStart;

/**
 * A course the family started without some of its first weeks (an academy
 * holiday, the teacher's leave: docs/architecture/02, section 27, J3) keeps
 * the days its first block needs to reach its last lesson: once its first
 * payment granted the block, the minutes last course_starts.
 * extra_validity_days longer (CreditsApi::extend, once by its key, as rule 15
 * gives a holiday's days back), and its lessons the new days now reach are
 * funded. Nothing for a course with no such days, one that joined a package's
 * balance, or one brought over by an import.
 */
final class GiveSkippedWeeksBack
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CreditsApi $credits,
        private readonly FundSessions $fund,
    ) {}

    public function handle(EnrollmentActivated $event): void
    {
        if ($event->reason !== 'paid') {
            return;
        }
        $days = (int) CourseStart::query()->where('enrollment_id', $event->enrollmentId)->value('extra_validity_days');
        $account = $days > 0 ? $this->enrollments->find($event->enrollmentId)?->creditAccountId : null;
        if ($account === null) {
            return;
        }
        if ($this->credits->extend($account, $days, "own-start:{$event->enrollmentId}", null, 'skipped_weeks') > 0) {
            $this->fund->handle($event->enrollmentId);
        }
    }
}
