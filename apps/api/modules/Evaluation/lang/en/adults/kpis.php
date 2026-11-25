<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'nav' => 'Trainer performance',
    'title' => 'Trainer performance',
    'subtitle' => 'The last 90 days. Each KPI against its target: green within it, red needs following up. Sessions the platform or an outage spoiled can be taken out of a trainer\'s numbers from their page.',
    'mine' => ['comments' => 'What trainees said'],
    'teacher' => 'Trainer',
    'names' => [
        'taught' => 'Time trained',
        'rating' => 'Trainee rating',
        'complaints' => 'Confirmed complaints per 100 sessions',
        'homework' => 'Tasks marked within 48h',
    ],
    'sample' => [
        'punctuality' => '{1} of one session|[2,*] of :count sessions',
        'absence' => '{1} of one session|[2,*] of :count sessions',
        'late_cancel' => '{1} of one session|[2,*] of :count sessions',
        'taught' => '{1} of one session|[2,*] of :count sessions',
        'early_leave' => '{1} of one session|[2,*] of :count sessions',
        'complaints' => '{1} over one session|[2,*] over :count sessions',
    ],
    'events' => [
        'late_cancel' => 'Cancelled too close to the session',
    ],
    'exclude_heading' => 'Take this session out of the trainer\'s numbers?',
    'exclude_hint' => 'The session will not count in any of the trainer\'s KPIs, and the reason is kept.',
    'errors' => ['lesson' => 'That session does not exist.'],
];
