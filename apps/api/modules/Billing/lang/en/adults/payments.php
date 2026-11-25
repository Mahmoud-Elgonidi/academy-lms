<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../payments.php.
return [
    'page' => [
        'student' => 'Trainee',
        'coupon' => [
            'offer' => 'Your offer after the free introductory session (:code) is on this invoice.',
        ],
        'result_hint' => [
            'paid' => 'Your sessions are confirmed, and the receipt is on its way.',
        ],
    ],
    'settings' => [
        'empty' => 'No gateways yet. Add one so trainees can pay online.',
    ],
];
