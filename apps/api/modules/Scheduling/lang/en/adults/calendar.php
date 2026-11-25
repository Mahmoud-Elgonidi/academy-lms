<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'subtitle' => 'Every session, on :zone time',
    'course' => 'Programme sessions on the calendar',
    'filters' => [
        'teacher' => 'All trainers',
        'student' => 'All trainees',
        'subject' => 'All areas',
    ],
    'totals' => [
        'lessons' => '{0} No sessions|{1} 1 session|[2,*] :count sessions',
        'hint' => 'Tap a status to see only its sessions',
    ],
    'statuses' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'reasons' => [
        'school' => 'Work',
        'family' => 'Personal matter',
        'teacher_leave' => 'Trainer on leave',
        'substitute_refused' => 'Trainee turned down the substitute',
    ],
    'by' => [
        'student' => 'by the trainee',
        'teacher' => 'by the trainer',
        'family' => 'by the payer',
    ],
    'legend' => [
        'off' => 'Trainer on leave',
    ],
    'more_hint' => ':count more sessions at this time: open the day',
    'empty' => [
        'title' => 'No sessions here',
        'day' => 'No sessions or shifts on this day.',
    ],
    'list' => [
        'more' => 'Showing the first :limit sessions. Narrow the filters, or download the Excel file with all of them.',
    ],
    'summary' => [
        'teachers' => 'By trainer',
        'subjects' => 'By area',
        'teacher' => 'Trainer',
        'subject' => 'Area',
        'hint' => 'Hours leave out cancelled sessions. Tap a trainer or area to see its calendar.',
        'empty' => 'No sessions in this period.',
    ],
    'details' => [
        'missing' => 'This session is not here',
        'teacher' => 'Trainer',
        'student' => 'Trainee',
        'subject' => 'Area',
        'account' => "Trainee's programme account",
        'teacher_calendar' => "Trainer's calendar",
        'student_calendar' => "Trainee's calendar",
    ],
    'export' => [
        'lessons' => 'Sessions',
        'title' => 'Sessions from :from to :to',
        'teacher' => 'Trainer',
        'subject' => 'Area',
        'student' => 'Trainee',
        'instead_of' => 'Instead of the trainer',
    ],
];
