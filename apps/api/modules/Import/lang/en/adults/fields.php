<?php

return [
    'teachers' => [
        'subjects' => 'Fields',
        'trials' => 'Trial sessions',
        'max_daily' => 'Most sessions a day',
    ],
    'families' => [
        'student_name' => 'Trainee name',
        'student_email' => 'Trainee email',
        'student_phone' => 'Trainee mobile',
    ],
    'schedules' => [
        'student_name' => 'Trainee name',
        'teacher' => 'Trainer',
        'subject' => 'Field',
        'duration' => 'Session length',
        'remaining' => 'Sessions left',
    ],
    'hints' => [
        'teachers' => [
            'subjects' => 'Separate with commas. A field the academy lacks is added.',
        ],
        'families' => [
            'student_phone' => 'The trainee signs in with it, or with their email: give at least one.',
        ],
        'schedules' => [
            'contact' => 'The trainee\'s number or email, as imported.',
            'teacher' => 'The trainer\'s email, number or name.',
            'clock' => 'Trainer (default), trainee, or a country.',
        ],
    ],
];
