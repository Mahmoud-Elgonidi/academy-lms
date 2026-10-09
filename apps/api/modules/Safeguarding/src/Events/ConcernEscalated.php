<?php

namespace Modules\Safeguarding\Events;

/** Nobody took a new concern within the hour: the leads hear again. */
final readonly class ConcernEscalated
{
    public function __construct(public string $academyId, public string $concernId) {}
}
