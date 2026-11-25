<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'nav' => 'Training programmes',
    'fields' => [
        'subject' => 'Field',
        'students' => 'Trainees',
    ],
    'hints' => [
        'objectives' => 'One objective per line. The trainer sees them in today\'s plan.',
    ],
    'mastery_rules' => [
        'mastered_once' => 'The trainer marks it "mastered" once',
    ],
    'builder' => [
        'empty_title' => 'Start building the programme',
    ],
    'preview' => [
        'action' => 'Preview as a trainee sees it',
        'title' => 'The programme as a trainee sees it',
        'hint' => 'Each level is an island, each lesson a station on its path. No trainee\'s data shows here.',
    ],
    'resources' => [
        'hint' => 'Files and links the trainer opens in the session, and the trainee reviews afterwards: a PDF page, a recording to listen to, a short video, or a link (a video on YouTube or Drive). Each one for everyone or for trainers only (a trainer\'s guide, the answers).',
        'audiences' => ['all' => 'Everyone in the session', 'teachers' => 'Trainers only'],
        'teachers_only' => 'Trainers only',
    ],
    'text' => [
        'hint' => 'What the trainee reads in this lesson. The AI helper makes practice from it.',
    ],
    'practice' => [
        'hint' => 'What the trainee practises after the lesson in the games: a question with choices, parts to put in order (a sentence’s words, steps), or pairs to match. Games take their words from here only, so write them exactly as the trainee should see them.',
    ],
    'errors' => [
        'in_use' => [
            'reports' => '“:title” cannot be deleted: session reports were written on it or on something under it, and deleting it would lose them. Rename it or move it elsewhere instead.',
            'progress' => '“:title” cannot be deleted: trainees have started it and have progress on it or on something under it. Rename it or move it elsewhere instead.',
            'homework' => '“:title” cannot be deleted: tasks given to trainees point at it or at something under it. Rename it or move it elsewhere instead.',
            'practice' => '“:title” cannot be deleted: trainees practised it or something under it and their results are kept. Rename it or move it elsewhere instead.',
        ],
    ],
];
