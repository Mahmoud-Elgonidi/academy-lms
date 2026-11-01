<?php

namespace Modules\Crm\Contracts\Events;

/** The academy cancelled a lead's free trial (a teacher's leave, a holiday, the office; raised after commit): the family hears how to book again. */
final readonly class TrialCancelledByAcademy
{
    public function __construct(public string $academyId, public string $trialId, public string $leadId, public string $reason) {}
}
