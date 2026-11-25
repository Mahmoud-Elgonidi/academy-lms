<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../reports.php.
return [
    'kinds' => ['lesson' => 'Session report'],
    'attributes' => [
        'notes_for_student' => 'notes for the trainee',
        'items' => 'what the session covered',
    ],
    'oversight' => [
        'subtitle' => 'Every finished session needs a report from the trainer within :hours hours. Here: late ones, ones waiting for review, and reviewed ones.',
        'teacher' => 'Trainer',
        'student' => 'Trainee',
        'lesson' => 'Session',
        'review_note' => 'Note for the trainer (optional)',
    ],
    'detail' => [
        'no_items' => 'No programme lines in this report (the trainee follows no programme).',
        'for_family' => 'For the trainee',
        'homework' => 'Task',
    ],
];
