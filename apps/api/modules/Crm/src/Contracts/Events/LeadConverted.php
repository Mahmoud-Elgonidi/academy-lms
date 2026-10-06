<?php

namespace Modules\Crm\Contracts\Events;

/** A lead's learner was enrolled: the lead is won. */
final readonly class LeadConverted
{
    public function __construct(public string $academyId, public string $leadId, public string $enrollmentId, public ?string $teacherId) {}
}
