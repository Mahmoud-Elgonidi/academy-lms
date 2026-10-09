<?php

namespace Modules\People\Checks\Events;

/** The academy verified a teacher's document, or turned it down with a reason the teacher reads. */
final readonly class TeacherCheckDecided
{
    public function __construct(public string $academyId, public string $checkId, public bool $verified) {}
}
