<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'subtitle' => 'Write to all the trainees or to the trainers at once. It reaches their notifications (the bell, the portal and the app), and a phone notification or an email if you choose.',
    'form' => [
        'audiences' => [
            'students' => 'Trainees',
            'teachers' => 'Trainers',
        ],
        'audience_hints' => [
            'students' => 'Every trainee with an active account.',
            'teachers' => 'Every trainer.',
        ],
    ],
    'reach' => [
        'note' => 'Active accounts only.',
    ],
];
