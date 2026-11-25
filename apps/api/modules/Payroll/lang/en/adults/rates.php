<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../rates.php.
return [
    'nav' => 'Trainer rates',
    'subtitle' => 'Each trainer is paid from one card; the default card covers everyone without one. The closest price wins: area and kind of session, then area, then kind, then any session. A new price applies to sessions from now on.',
    'fields' => [
        'format' => 'Sessions',
        'is_default_hint' => 'Trainers without a card of their own are paid from it.',
        'subject' => 'Area',
        'any_subject' => 'Any area',
        'type' => 'Kind of session',
        'teachers' => 'Trainers on this card',
        'teachers_hint' => 'A trainer is on one card; choosing them here moves them from their old one.',
    ],
    'formats' => ['group' => 'Group (fixed rate per session)'],
    'units' => ['lesson' => 'By the session'],
    'unit_hints' => ['hour' => 'paid by the minute of the session', 'lesson' => 'a flat amount per session'],
    'columns' => ['teachers' => 'Trainers'],
    'empty' => [
        'hint' => 'Add a card with an hourly or per-session price, so trainers\' pay is recorded with every session.',
    ],
    'errors' => [
        'type' => 'Unknown kind of session.',
        'twice' => 'The same area and kind appear twice.',
    ],
];
