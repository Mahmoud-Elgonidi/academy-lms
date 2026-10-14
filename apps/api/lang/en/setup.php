<?php

// "Start here": what a new academy gets ready before its first families (each module words its own steps).
return [
    'nav' => 'Start here',
    'title' => 'Start here',
    'subtitle' => 'What must be ready before the first families come. Each step ticks itself once it is done.',
    'progress' => [
        'title' => 'Essentials ready',
        'of' => 'of :total',
        'left' => '{1} One step left and your academy is ready.|[2,*] :count steps left and your academy is ready.',
        'complete' => 'All the essentials are ready: your academy is set for its first families. This page stays in Settings if you need it.',
        'cheer' => 'Nice!',
    ],
    'optional' => [
        'title' => 'When you are ready',
        'hint' => 'Not needed to start, but they make the work easier.',
    ],
    'state' => [
        'done' => 'Done',
        'todo' => 'Not yet',
        'locked' => 'Not in your access: someone on the team who has it does this.',
    ],
    'actions' => [
        'start' => 'Set up',
        'open' => 'Open',
    ],
];
