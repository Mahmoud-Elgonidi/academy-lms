<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../family.php.
return [
    'nav' => ['classic' => 'Sessions', 'kids' => 'My sessions', 'middle' => 'My sessions', 'teen' => 'My sessions'],
    'title' => 'Sessions',
    'recent' => 'Recent sessions',
    'none_upcoming' => 'No sessions in the next four weeks.',
    'none_recent' => 'No sessions in the last two weeks.',
    'join' => 'Join the session',
    'opens_at' => 'The session room opens at :time',
    'statuses' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent: a makeup is yours',
    ],
    'cancel' => [
        'button' => 'Cancel the session',
        'heading' => 'Cancel this session?',
        'not_now' => 'This session cannot be changed now: it has started or is over.',
        'done' => 'The session is cancelled; the trainer and the academy know.',
        'reasons' => [
            'school' => 'Work or exams',
            'family' => 'Personal matter',
        ],
        'outcomes' => [
            'refund' => 'More than :hours hours before the session: it goes back to your balance.',
            'late' => 'Less than :hours hours before the session: it counts against your balance.',
            'trial' => 'This is an introductory session: it is cancelled at no cost.',
            'makeup' => 'This is a makeup session: once cancelled it is not made up again.',
        ],
    ],
    'widget' => [
        'all' => 'All sessions',
        'none' => 'No sessions coming up.',
    ],
];
