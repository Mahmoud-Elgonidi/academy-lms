<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'categories' => ['lessons' => 'Sessions', 'learning' => 'Reports and tasks', 'staff' => 'Trainers\' and staff work'],
    'preferences' => [
        'categories' => [
            'lessons' => 'Session notifications',
            'learning' => 'Report and task notifications',
        ],
        'hints' => [
            'lessons' => 'Reminders before a session, "join now", cancellations and moves.',
            'learning' => 'Each session report, new tasks and their marking.',
            'staff' => 'Shift proposals, overdue reports, tasks handed in.',
        ],
    ],
];
