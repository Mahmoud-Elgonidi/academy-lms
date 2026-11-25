<?php

// Settings › Academy modules in an academy of adults only (docs/architecture/02, section 29): only the lines that differ.
return [
    'core' => [
        'scheduling' => ['label' => 'Sessions and times', 'line' => 'Weekly times, the calendar, time off, and the cancellation and makeup policy.'],
        'people' => ['label' => 'Trainers and trainees', 'line' => 'Trainers and trainees and their accounts.'],
        'classroom' => ['label' => 'Live session room', 'line' => "The session's room, attendance and recordings."],
        'billing' => ['label' => 'Invoices and paying by hand', 'line' => 'Plans and prices, invoices and transfer proofs.'],
        'reports' => ['label' => 'Reports and tasks', 'line' => "Each session's report and its questions, tasks and marking."],
        'family' => ['label' => 'Trainee portal', 'line' => "The trainee's sessions, reports and invoices in the portal and the app."],
        'safeguarding' => ['label' => 'Safety and conduct', 'line' => "Raising a problem or misconduct, and trainers' checks."],
    ],
    'where' => [
        'groups' => ['Groups and the group session settings in the back office', 'Group mode in “Find a trainer”', 'Open groups when a trainee starts a programme on their own'],
        'curriculum' => ['Training programmes in the back office', "The learning path and the programme's plan for trainees, in the portal and the app", "The week's chest"],
        'games' => ['Game settings in the back office', 'Games for trainees', 'Tasks as a game for trainers'],
        'messaging' => ['Conversations in the back office', 'Messages between trainers and trainees, in the portal and the app', 'New message notifications'],
        'support' => ['Help in the back office', '“Help” for trainees and trainers, in the portal and the app', 'Late answer reminders'],
        'evaluation' => ["Trainers' performance in the back office", '“My performance” for trainers', "Trainees' session ratings"],
        'site' => ["The academy's page and its requests in the back office", 'The public page, and the introductory session and training requests from it', '“My card” for trainers'],
    ],
    'errors' => [
        'needs' => '“:module” works on the programme\'s units. Switch on “:needs” first, then “:module”.',
        'in_use' => [
            'groups' => '{1} The academy has one group with trainees on running programmes. End it or move its trainees to one-to-one sessions first, then switch off “:module”.|[2,*] The academy has :count groups with trainees on running programmes. End them or move their trainees to one-to-one sessions first, then switch off “:module”.',
        ],
    ],
    'setup' => [
        'hint' => 'Switch off what you do not use, such as group sessions or games, so everyone\'s menus stay simpler. You can bring it back at any time.',
    ],
];
