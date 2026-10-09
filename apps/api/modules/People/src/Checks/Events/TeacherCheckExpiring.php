<?php

namespace Modules\People\Checks\Events;

/** A verified document runs out within a month (or already ran out: $expired), and nothing newer takes over. */
final readonly class TeacherCheckExpiring
{
    public function __construct(public string $academyId, public string $checkId, public bool $expired = false) {}
}
