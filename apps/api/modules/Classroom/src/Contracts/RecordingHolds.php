<?php

namespace Modules\Classroom\Contracts;

/**
 * Lessons whose recordings must be kept past their days because something
 * about them is still open (a complaint, a safeguarding concern). The
 * classroom asks before removing a recording; each module that holds some
 * tags its own implementation SOURCES (Support: open complaints; Safeguarding:
 * open concerns), and the classroom asks them all. With none, nothing is held.
 */
interface RecordingHolds
{
    /** The container tag every module's own holds go under. */
    public const SOURCES = 'classroom.recording-holds';

    /**
     * @param  list<string>  $sessionIds
     * @return array<string, string> lesson id => why it is held (for the screen)
     */
    public function heldSessions(array $sessionIds): array;
}
