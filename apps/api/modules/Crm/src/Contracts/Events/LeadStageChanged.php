<?php

namespace Modules\Crm\Contracts\Events;

/** A lead moved from one stage to another (raised after commit). */
final readonly class LeadStageChanged
{
    public function __construct(public string $academyId, public string $leadId, public string $from, public string $to) {}
}
