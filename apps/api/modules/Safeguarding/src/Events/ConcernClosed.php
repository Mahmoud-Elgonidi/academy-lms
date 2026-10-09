<?php

namespace Modules\Safeguarding\Events;

/** A lead closed a concern; the reporter reads the reply, if the lead wrote one. */
final readonly class ConcernClosed
{
    public function __construct(public string $academyId, public string $concernId) {}
}
