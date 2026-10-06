<?php

namespace Modules\Learning\Contracts\Events;

/** A report passed its deadline without being handed in; the teacher and the supervisor are told. */
final readonly class SessionReportOverdue
{
    public function __construct(
        public string $academyId,
        public string $reportId,
        public string $sessionId,
        public string $teacherId,
    ) {}
}
