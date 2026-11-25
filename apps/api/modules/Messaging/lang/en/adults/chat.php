<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'sides' => ['teacher' => 'Trainer', 'student' => 'Trainee'],
    'why_not' => [
        'ended' => 'This trainer no longer trains this trainee, so the conversation is read only.',
    ],
    'errors' => [
        'not_teaching' => 'You can only write to trainers who train you now.',
    ],
];
