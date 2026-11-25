<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'ways' => [
        'copy' => ['label' => 'Copy a programme', 'hint' => 'From another programme of the academy.'],
    ],
    'ai' => [
        'heading' => 'A programme outline with AI',
        'fields' => [
            'goal_hint' => 'What does the trainee master by the end of the programme? Do not write anyone\'s name.',
            'goal_placeholder' => 'For example: run a small project with planning and tracking tools, in three months.',
        ],
    ],
    'copy' => [
        'heading' => 'Copy a programme',
        'errors' => [
            'not_empty' => 'A programme is copied into an empty programme that is not published yet.',
        ],
    ],
    'sheet' => [
        'name' => 'Programme outline',
    ],
    'errors' => [
        'levels' => 'A programme holds at most :levels levels.',
        'lessons' => 'A programme holds at most :lessons lessons.',
    ],
];
