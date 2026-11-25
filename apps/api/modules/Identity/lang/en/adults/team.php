<?php

return [
    'subtitle' => 'Who works in the back office and what each of them may do. Trainers and trainees are added under People.',
    'role_hints' => [
        'admin' => 'Runs the academy day to day: people, sessions, settings, and the team up to admin.',
        'scheduler' => 'Timetables, booking and sessions, time off, leads.',
        'sales' => 'Leads and trial sessions.',
    ],
    'invite' => [
        'who_options' => ['teacher' => 'A trainer already here'],
        'teacher' => 'Trainer',
        'teacher_hint' => 'They keep training; the roles you choose are added to their account.',
        'joined' => ':name joined the team and keeps training.',
    ],
    'actions' => [
        'roles_hint' => 'What they may do changes from their next step. If they train, they keep training.',
    ],
    'errors' => [
        'not_staff_role' => 'Trainer and trainee are managed under People, not here.',
        'also_other' => 'This account also trains or is a trainee: take away its back-office roles here, or turn it off from its page under People.',
    ],
];
