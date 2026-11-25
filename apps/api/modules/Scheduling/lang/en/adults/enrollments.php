<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../enrollments.php.
return [
    'look' => [
        'action' => 'Look',
        'title' => 'The look of the trainee\'s area',
        'hint' => 'The trainee\'s web area (/my) changes its look and tone with the look chosen. The classic look suits adults, unless you choose another.',
        'stages' => [
            'auto' => 'Automatic',
            'kids' => 'Simple journey',
            'middle' => 'Points and badges',
            'teen' => 'Plan and goals',
            'classic' => 'Classic',
        ],
        'hints' => [
            'auto' => 'Adults get the classic look by themselves.',
            'middle' => 'Levels, points, a session streak and badges.',
        ],
        'done' => 'Saved. The trainee\'s area now looks: :stage.',
    ],
    'nav' => 'Enrolled trainees',
    'open' => 'Trainee account',
    'student_clock' => 'Trainee\'s time',
    'row' => [
        'cancel_title' => 'Cancel the session of :when',
        'reschedule_title' => 'Move the session of :when',
        'reschedule_submit' => 'Move the session',
        'rescheduled' => 'Session moved to :when.',
    ],
    'picker' => [
        'time' => 'Time (trainer\'s time beside it)',
        'none' => 'The trainer has no free time that day',
    ],
    'makeup' => [
        'title' => 'Makeup session',
        'hint' => ':minutes min of makeup credit valid until :date, with the same trainer at a free time.',
    ],
    'extra' => [
        'action' => 'Book an extra session',
        'title' => 'Extra session',
        'hint' => 'The programme has :minutes paid minutes free beyond its booked sessions. An extra session with the same trainer at a free time takes :length of them.',
        'submit' => 'Book the extra session',
        'done' => 'Extra session booked: :date.',
    ],
    'reassign' => [
        'action' => 'Change trainer',
        'title' => 'Move the trainee to another trainer',
        'hint' => 'The trainee keeps the same times, and booked sessions move with their credit. The new trainer must be free for every session from that day.',
        'teacher' => 'New trainer',
        'submit' => 'Move the trainee',
        'done' => 'Moved: :moved sessions with the new trainer.',
    ],
    'fields' => [
        'student' => 'Trainee',
    ],
    'session_statuses' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'weekly' => [
        'hint' => 'In :student time, the trainer\'s (:teacher) underneath. The time is fixed on the trainer\'s clock.',
        'teacher' => 'Trainer: :day :time',
    ],
    'invoices' => [
        'family' => 'combined invoice',
    ],
    'empty' => [
        'title' => 'No enrolled trainees yet',
        'hint' => 'Enrol from "Find a trainer": pick the times, hold them, then "Enrol the trainee".',
    ],
];
