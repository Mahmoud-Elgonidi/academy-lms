<?php

namespace Modules\Payroll\Listeners;

use Modules\Payroll\Actions\AccrueLessonPay;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Contracts\Events\SessionFlaggedForReview;

/**
 * Keeps the pay ledger in step with the lessons (docs/architecture/04:
 * Payroll listens to SessionFinalized): a lesson decided or cancelled with a
 * share of pay gets its line; one sent back for review loses it until it is
 * decided again.
 */
final class AccruePay
{
    public function __construct(private readonly AccrueLessonPay $accrue) {}

    public function handle(SessionFinalized|SessionCancelled|SessionFlaggedForReview $event): void
    {
        $this->accrue->handle($event->sessionId);
    }
}
