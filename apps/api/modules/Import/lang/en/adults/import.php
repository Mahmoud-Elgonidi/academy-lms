<?php

// An academy that trains adults only (docs/architecture/02, section 29): trainees alone, no guardians.
return [
    'subtitle' => 'Bring your trainers, trainees and their schedules in from Excel or CSV sheets. Before anything is written you see what each row will do.',
    'kinds' => [
        'teachers' => ['title' => 'Trainers', 'hint' => 'Name, email or mobile, gender and fields. A field the academy lacks is added.'],
        'families' => ['title' => 'Trainees', 'hint' => 'A row per trainee, with their own email or mobile: every trainee signs in to their own account.'],
        'schedules' => ['hint' => 'Trainee, trainer, field and weekly times, with the sessions left of what they paid. Sessions ahead are made for you.'],
    ],
    'order' => 'In this order: trainers, then trainees, then schedules.',
    'columns' => [
        'invite_hint' => 'On WhatsApp and email.',
    ],
];
