<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Contracts\Events\EnrollmentActivated;
use Modules\Enrollment\Models\Enrollment;

/** The first payment arrived. Anything but "awaiting payment" is left as it is. */
final class ActivateEnrollment
{
    public function handle(string $enrollmentId, ?string $actorId = null): void
    {
        DB::transaction(function () use ($enrollmentId, $actorId) {
            $enrollment = Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->firstOrFail();
            if ($enrollment->status !== EnrollmentStatus::PendingPayment) {
                return;
            }

            $enrollment->moveTo(EnrollmentStatus::Active, 'paid', $actorId);
            DB::afterCommit(fn () => event(new EnrollmentActivated($enrollment->academy_id, $enrollment->id)));
        });
    }
}
