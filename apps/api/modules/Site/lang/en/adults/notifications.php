<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// request_received says what request_received_adults says: the whole academy speaks to the one asking for themself.
return [
    'audience' => [
        'requester' => 'Whoever asked for an introductory session on the academy\'s page (by WhatsApp and email: they have no account yet)',
        'staff' => 'Who answers it (sales for introductory sessions, who runs the trainers for training)',
    ],
    'kinds' => ['trial' => 'a free introductory session', 'teach' => 'to train with you'],
    'request_received' => [
        'body' => 'Thank you for asking for a free introductory session. We will be in touch within :hours hours. :next',
        'pick' => 'Or choose your session\'s time yourself here: :link',
    ],
];
