<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../enrollment.php.
return [
    'errors' => [
        'program' => 'This training content is for another area or has no published version yet.',
        'not_pausable' => [
            'pending_payment' => 'This programme is waiting for its first payment: it cannot be paused. End it, or wait for the payment.',
            'ended' => 'This programme has ended.',
            'paused' => 'This programme is paused.',
            'active' => 'This programme cannot be paused.',
        ],
        'ending' => 'This programme ends on :date already.',
        'ended' => 'This programme has ended. A new enrolment is the way back.',
        'pause_longer' => 'The programme is paused until :date: a pause can only be made longer. To change it otherwise, resume the programme and pause it again.',
        'not_paused' => 'This programme is not paused.',
        'end_day' => 'The programme ends today or on a later day, within a year.',
    ],
];
