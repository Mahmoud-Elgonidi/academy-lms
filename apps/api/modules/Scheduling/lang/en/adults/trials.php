<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../trials.php.
// The trainee's own free introductory session: written to them.
return [
    'errors' => [
        'too_soon' => 'This time is too close or already past: a session is booked at least :hours hours ahead. Please choose a later time.',
        'too_late_to_move' => 'The session cannot be moved less than :hours hours before it starts. If you cannot attend, cancel it from the same link and ask for a new time.',
        'moved_enough' => 'You have moved this session :times times, which is the most we allow. If the time does not suit you, cancel it and ask for a new introductory session: we will help you find a time that works.',
        'teacher_not_taking' => 'This session\'s trainer no longer takes new introductory sessions, so it cannot be moved. The session stays at its time; if it does not suit you, cancel it and ask for a new one with another trainer.',
    ],
];
