<?php

namespace Modules\People\Checks\Events;

/** A teacher sent a document for the academy to check. */
final readonly class TeacherCheckSubmitted
{
    public function __construct(public string $academyId, public string $checkId) {}
}
