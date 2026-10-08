<?php

namespace Modules\Support\Contracts\Events;

/**
 * The academy confirmed a complaint about a teacher: it counts in the
 * teacher's complaints KPI (Evaluation reads it through its TeacherComplaints
 * contract), and the teacher hears that it was confirmed, without the family's name.
 */
final readonly class ComplaintValidated
{
    public function __construct(public string $academyId, public string $ticketId, public string $teacherId, public ?string $sessionId) {}
}
