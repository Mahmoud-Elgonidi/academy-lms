<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../homework.php.
return [
    'attributes' => ['feedback' => 'trainer feedback'],
    'practice' => [
        'with_hint' => 'The trainee practises the items of this report’s lessons in the games, and you see how it went here.',
        'by_playing' => 'This task hands itself in as soon as you finish its round in the game.',
    ],
    'errors' => [
        'closed' => 'This task has already been marked.',
        'not_submitted' => 'This task has not been handed in yet.',
        'no_practice' => 'This task has no practice in the games.',
        'by_playing' => 'This task is handed in by playing its round in the game.',
    ],
    'game' => [
        'page' => 'Practice game for :student',
        'kicker' => 'After the session of :when',
        'open' => 'Practice game',
        'taught' => 'Covered in the session (from the report)',
        'before_next' => 'Due before the next session',
        'only' => 'The game is the whole task (handed in by playing)',
        'sent' => 'Sent to :student. It shows on their journey as a task.',
        'errors' => ['no_fit' => 'This game does not suit the trainee, or these lessons do not have enough practice for it.'],
    ],
];
