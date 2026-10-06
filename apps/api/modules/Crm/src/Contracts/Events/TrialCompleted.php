<?php

namespace Modules\Crm\Contracts\Events;

/** A lead attended their trial lesson; its teacher is asked to assess it. */
final readonly class TrialCompleted
{
    public function __construct(public string $academyId, public string $trialId, public string $leadId, public string $teacherId) {}
}
