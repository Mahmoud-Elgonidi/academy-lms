<?php

namespace Modules\Classroom\Contracts;

/**
 * Lessons whose recordings must be kept past their days because something
 * about them is still open (a complaint). The classroom asks before removing
 * a recording; Support binds it (open complaints about the lesson). Unbound,
 * nothing is held.
 */
interface RecordingHolds
{
    /**
     * @param  list<string>  $sessionIds
     * @return array<string, string> lesson id => why it is held (for the screen)
     */
    public function heldSessions(array $sessionIds): array;
}
