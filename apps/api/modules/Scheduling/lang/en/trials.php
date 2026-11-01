<?php

// A family's own free trial: booked from the academy's page, moved or cancelled from their own link.
return [
    'errors' => [
        'taken' => 'Someone took this time a moment ago. The list of times is updated: please choose another one.',
        'too_soon' => 'This time is too close or already past: a lesson is booked at least :hours hours ahead. Please choose a later time.',
        'too_late_to_move' => 'The lesson cannot be moved less than :hours hours before it starts. If you cannot come, press "We cannot come" and ask for a new time.',
        'moved_enough' => 'You have moved this lesson :times times, which is the most we allow. If the time does not suit you, cancel it and ask for a new trial: we will help you find a time that works.',
        'teacher_not_taking' => 'This lesson\'s teacher no longer takes new trial lessons, so it cannot be moved. The lesson stays at its time; if it does not suit you, cancel it and ask for a new one with another teacher.',
    ],
];
