<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../shifts.php.
return [
    'roles' => ['teacher' => 'the trainer'],
    'errors' => [
        'reason_required' => 'Write the reason so the trainer knows.',
        'teacher_drafting' => 'The trainer is drafting a proposal; wait until it is sent.',
    ],
    'comments' => [
        'withdrawn' => 'The trainer withdrew the proposal.',
    ],
];
