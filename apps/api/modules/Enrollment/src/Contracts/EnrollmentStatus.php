<?php

namespace Modules\Enrollment\Contracts;

enum EnrollmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    /** Its weekly times stay booked and its sessions keep being generated. */
    public function keepsSlots(): bool
    {
        return in_array($this, [self::PendingPayment, self::Active], true);
    }
}
