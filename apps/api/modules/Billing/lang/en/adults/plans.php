<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../plans.php.
return [
    'formats' => [
        'private' => [
            'line' => 'One trainer and one trainee in the session, at times of the trainee\'s own.',
        ],
        'group' => [
            'line' => 'Several trainees of the same level with one trainer at fixed times (up to :max), cheaper for each trainee.',
        ],
    ],
    'compare' => [
        'less' => 'A group session in :region costs :lesson, :pct% less than a private session on ":plan" (:private).',
        'more' => 'A group session in :region costs :lesson, :pct% more than a private session on ":plan" (:private). Price it below the private one.',
        'same' => 'A group session in :region costs as much as a private session on ":plan" (:private). Price it below the private one.',
    ],
    'scopes' => [
        'enrollment' => 'Per programme',
    ],
    'fields' => [
        'lesson_format' => 'Session format',
        'countries_hint' => "The countries this region's payers live in. The academy's page shows the prices of the region with the visitor's country. A country is in one region only.",
    ],
    'hints' => [
        'credit_scope' => 'A shared package is one balance for a trainee in any area (say 12 sessions a month across project management and financial analysis). Each new programme of theirs on it draws on the same balance, with no new invoice, and the sessions a week here are for all their areas together. It stays as it is once someone is on it.',
        'validity' => 'How long the block\'s sessions can be used: the weeks it needs plus two weeks\' grace. Sessions left after that expire.',
        'format_locked' => 'Trainees are on this plan, so its session format stays as it is. For the other format, make a new plan.',
        'shape' => 'Session length, sessions a week and sessions per block stay as they are once someone is on it. For another shape, make a new plan.',
        'renew' => 'When the trainee\'s balance is down to this many sessions, the payer gets the next block\'s invoice, so they can pay before the sessions run out and nothing stops.',
    ],
    'self_serve' => [
        'action' => 'Trainees start on their own',
        'title' => 'Trainees start a programme and pay on their own',
        'hint' => 'From their account in the portal or the app: they choose the plan, the trainer and the times, get the invoice and its pay link, and the trial offer is applied for them. Off, trainees read that the office enrols them; what they started stays as it is.',
        'label' => 'The academy lets trainees start a programme on their own',
    ],
    'errors' => [
        'scope' => 'A plan is either per programme or a shared package.',
        'scope_locked' => 'Trainees are on this plan, so whether it is per programme or shared stays as it is. Make a new plan.',
        'format' => 'Sessions are either private or group.',
        'format_locked' => 'Trainees are on this plan, so its sessions stay private or group as they are. Make a new plan.',
        'group_shared' => 'A group plan cannot be a shared package; choose "per programme".',
        'shape_locked' => 'Trainees are on this plan, so its session length, sessions a week and sessions per block stay as they are. Make a new plan.',
    ],
];
