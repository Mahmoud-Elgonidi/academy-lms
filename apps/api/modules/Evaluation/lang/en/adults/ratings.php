<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'ask' => 'How was the session?',
    'tags' => [
        'engaging' => 'Useful session',
        'late' => 'Trainer was late',
    ],
    'page' => [
        'title' => 'Rate the session',
        'heading' => 'How was your session?',
        'change' => 'You can change your rating for two weeks after the session.',
        'private' => 'Trainers see ratings without names.',
    ],
    'errors' => [
        'not_yours' => 'This session is not yours.',
        'not_held' => 'This session did not take place, so it cannot be rated.',
        'too_old' => 'Sessions can be rated for :days days only.',
    ],
];
