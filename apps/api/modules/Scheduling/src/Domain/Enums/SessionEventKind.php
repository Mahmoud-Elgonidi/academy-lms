<?php

namespace Modules\Scheduling\Domain\Enums;

/** Entries in a session's log. */
enum SessionEventKind: string
{
    case Scheduled = 'scheduled';
    case Joined = 'joined';
    case Left = 'left';
    case ConnectionDrop = 'connection_drop';
    case ContactLogged = 'contact_logged';
    case DecisionLogged = 'decision_logged';
    case Extended = 'extended';
    case StatusChanged = 'status_changed';
    case PolicyApplied = 'policy_applied';
    case Rescheduled = 'rescheduled';
    case TeacherChanged = 'teacher_changed';
}
