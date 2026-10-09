<?php

namespace Modules\Safeguarding\Events;

/** A concern came in: the safeguarding leads hear at once. */
final readonly class ConcernReported
{
    public function __construct(public string $academyId, public string $concernId) {}
}
