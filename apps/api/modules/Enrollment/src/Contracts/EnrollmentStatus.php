<?php

namespace Modules\Enrollment\Contracts;

enum EnrollmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    /** Its weekly times stay booked with its teacher: a paused course keeps them too, an ended one has let them go. */
    public function keepsSlots(): bool
    {
        return in_array($this, [self::PendingPayment, self::Active, self::Paused], true);
    }

    /** Lessons are made from its weekly times (not while paused, never once ended). */
    public function makesLessons(): bool
    {
        return in_array($this, [self::PendingPayment, self::Active], true);
    }

    /** @return list<string> the statuses whose weekly times become lessons */
    public static function making(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->makesLessons())));
    }
}
