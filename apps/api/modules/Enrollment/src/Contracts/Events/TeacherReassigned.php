<?php

namespace Modules\Enrollment\Contracts\Events;

final readonly class TeacherReassigned
{
    public function __construct(public string $academyId, public string $enrollmentId, public string $fromTeacherId, public string $toTeacherId, public string $fromDate) {}
}
