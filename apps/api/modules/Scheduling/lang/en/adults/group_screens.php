<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'card' => [
        'held' => '{0} No sessions held yet|{1} 1 session held|[2,*] :n sessions held',
        'waits' => '{1} Waits for 1 more trainee to start|[2,*] Waits for :n more trainees to start',
        'starts_first' => 'Starts with its first trainee',
        'forming_line' => 'Forming: its sessions start once it has its trainees',
    ],

    'list' => [
        'subtitle' => 'Group sessions: one trainer and several trainees of the same level at fixed times.',
        'filters' => [
            'subject' => 'Area',
            'all_subjects' => 'All areas',
            'teacher' => 'Trainer',
            'all_teachers' => 'All trainers',
        ],
        'none' => [
            'hint' => 'Change the area, trainer or status, or clear the filters.',
        ],
        'empty' => [
            'hint' => 'A group is weekly sessions several trainees take with one trainer, on a group plan cheaper than a private one. Make one from "Find a trainer" in group mode: pick the area, the times and a trainer who trains groups.',
            'points' => [
                'teacher' => ['title' => 'A trainer who trains groups', 'line' => 'Turn on "Trains groups" in the trainer\'s profile and set the most trainees.'],
                'seats' => ['line' => 'Each group has its seats and weekly times; each trainee in it has their own programme and balance.'],
            ],
        ],
    ],

    'details' => [
        'figs' => [
            'shape' => 'Sessions',
            'next' => 'Next session',
        ],
        'forming_waits' => '{1} The group waits for 1 more trainee to start. No sessions are made before.|[2,*] The group waits for :n more trainees to start. No sessions are made before.',
        'forming_first' => 'The group starts with the first trainee who joins.',
        'add' => 'Add a trainee',
        'members' => [
            'title' => 'Trainees',
            'privacy' => 'Fellow trainees see each other by first name and the initial of the last name only.',
        ],
        'seats' => [
            'capacity_hint' => 'From :min (the trainees in it) to :max (the trainer\'s most).',
            'no_groups' => 'The trainer no longer trains groups; seats cannot grow.',
            'students' => 'trainees',
            'first' => 'First trainee',
            'min_hint' => 'The trainees the group waits for before its sessions start. Leave it empty to start with the first.',
        ],
        'next' => [
            'title' => 'Next sessions',
            'forming' => 'Sessions are made once the group starts.',
            'none' => 'No sessions ahead.',
            'seated' => '{0} no trainees|{1} 1 trainee|[2,*] :n trainees',
        ],
        'end' => [
            'reason_hint' => 'Kept in the activity log and on each trainee\'s programme.',
            'done_body' => '{0} Its trainees\' programmes ended (:courses); it had no sessions ahead.|{1} Its trainees\' programmes ended (:courses); 1 session was cancelled and its minutes went back.|[2,*] Its trainees\' programmes ended (:courses); :n sessions were cancelled and their minutes went back.',
            'effects' => [
                'courses' => '{0} No trainees are in the group now.|{1} Its one trainee\'s programme ends today (:date).|[2,*] Its :n trainees\' programmes end today (:date).',
                'lessons' => '{0} No sessions ahead to cancel.|{1} The next session is cancelled and its minutes go back to the trainees\' balances.|[2,*] :n sessions ahead are cancelled and their minutes go back to the trainees\' balances.',
                'times' => 'Its weekly times end, so the trainer is free then.',
                'billing' => 'Next blocks\' invoices with nothing paid are closed, as for any programme that ends.',
            ],
        ],
    ],

    'settings' => [
        'nav' => 'Group sessions',
        'title' => 'Group sessions',
        'subtitle' => 'What a trainee joining a group that began gets, who trains together, and when a new group starts.',
        'saved' => 'Group session settings saved',
        'errors' => [
            'min' => 'The trainees a group waits for is 1 to 20.',
        ],
        'late' => [
            'hint' => 'The default when a trainee joins a group that held sessions of its current block. It can be changed at each join.',
        ],
        'ages' => [
            'title' => 'Who trains together',
            'hint' => 'A group takes trainees of the same age group and level.',
            'mixed_hint' => 'When on, a group takes trainees of different age groups.',
            'warning_title' => 'Different age groups together',
            'warning' => 'With this on, one group may hold trainees of different age groups; make sure this suits the academy\'s policy.',
        ],
        'start' => [
            'hint' => 'A new group waits for this many trainees before its sessions are made. Each group can change it.',
            'min' => 'Default trainees to start',
            'first' => 'First trainee',
            'min_hint' => 'Leave it empty to start a group with its first trainee.',
            'students' => 'trainees',
        ],
    ],

    'late_join' => [
        'discount' => ['line' => 'The missed sessions come off the first invoice at the plan\'s session price.'],
        'private' => ['line' => 'Makeup credit for the missed sessions\' minutes, booked as private sessions with the group\'s trainer within :days days.'],
        'group' => ['line' => 'Makeup credit for group sessions: a free seat in another group of the area within :days days.'],
        'none' => ['line' => 'The trainee pays the full block and starts from the next session.'],
    ],

    'find' => [
        'mode' => [
            'series' => ['line' => 'Weekly programme: one trainer and one trainee'],
            'trial' => ['title' => 'Introductory session', 'line' => 'One free session to try the trainer'],
        ],
        'open' => [
            'hint' => 'For the area, level and the trainee\'s age group, times in :city. With the trainee\'s hours marked, the groups at those hours.',
            'wait' => 'Pick the area to see its groups.',
            'none' => [
                'title' => 'No group in this area has a free seat',
            ],
        ],
        'steps' => [
            'teachers' => 'Trainers who train groups',
        ],
        'teacher_max' => '{2} Trains groups of up to 2|[3,*] Trains groups of up to :n',
        'no_group_teachers' => [
            'title' => 'No trainer in this area trains groups',
            'hint' => 'Turn on "Trains groups" in a trainer\'s profile on the Trainers screen.',
        ],
        'create' => [
            'submit_join' => 'Make the group and add the trainee',
            'capacity_hint' => 'Up to :max, the trainer\'s most.',
            'first' => 'First trainee',
            'min_hint' => 'Optional: the trainees it waits for before its sessions start.',
            'first_age' => 'Its first trainee\'s',
        ],
        'join' => [
            'student_first' => 'Pick the trainee first, in step one.',
            'forming' => 'Forming: its sessions start once it has its trainees',
            'plan_block' => '{1} A block of 1 session|[2,*] A block of :n sessions',
            'submit' => 'Add the trainee and issue the invoice',
            'late' => [
                'title' => '{1} Missed 1 session|[2,*] Missed :n sessions',
                'hint' => 'The sessions of the current block held before they join (since the group began: :held). Pick what makes up for them.',
                'lessons' => 'Sessions made up for',
            ],
            'done_late' => [
                'discount' => '{1} 1 missed session came off the invoice.|[2,*] :n missed sessions came off the invoice.',
                'private' => '{1} They have private makeup credit for 1 session.|[2,*] They have private makeup credit for :n sessions.',
                'group' => '{1} They have group makeup credit for 1 session.|[2,*] They have group makeup credit for :n sessions.',
                'none' => '{1} No makeup for the missed session.|[2,*] No makeup for the missed sessions.',
            ],
        ],
    ],
];
