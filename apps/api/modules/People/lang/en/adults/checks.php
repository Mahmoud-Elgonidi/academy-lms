<?php

// An academy that trains adults only: no rule for under-18s, the documents alone.
return [
    'errors' => [
        'not_teacher' => 'This is not a trainer of the academy.',
        'reason' => 'Write why it was turned down (the trainer reads it).',
    ],
    'office' => [
        'nav' => 'Trainer checks',
        'title' => 'Trainer checks',
        'lead' => 'The documents your trainers\' vetting rests on: check each against the original, and set until when it is valid.',
        'teacher' => 'Trainer',
        'actions' => [
            'add_hint' => 'For a trainer who brought it in. If you have seen the original, verify it now.',
            'verify_now_hint' => 'It counts at once; otherwise it waits here like one the trainer sent.',
            'reject_hint' => 'The trainer reads your reason and can send it again.',
        ],
        'done' => ['rejected' => 'Turned down: the trainer was told why'],
    ],
    'mine' => [
        'lead' => 'The academy checks these documents against the original, and keeps them on your trainer file.',
    ],
];
