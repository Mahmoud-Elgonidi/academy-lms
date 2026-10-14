<?php

return [
    'errors' => [
        'program' => 'This program is for another subject or has no published version yet.',
        'reason' => 'Write why, in up to 500 characters.',
        'not_pausable' => [
            'pending_payment' => 'This course is waiting for its first payment: it cannot be paused. End it, or wait for the payment.',
            'ended' => 'This course has ended.',
            'paused' => 'This course is paused.',
            'active' => 'This course cannot be paused.',
        ],
        'ending' => 'This course ends on :date already.',
        'ended' => 'This course has ended. A new enrollment is the way back.',
        'pause_dates' => 'The last paused day comes on or after the first.',
        'pause_too_long' => 'A pause is at most :days days.',
        'pause_longer' => 'The course is paused until :date: a pause can only be made longer. To change it otherwise, resume the course and pause it again.',
        'pause_past' => 'A pause starts today or later.',
        'not_paused' => 'This course is not paused.',
        'end_day' => 'The course ends today or on a later day, within a year.',
    ],
];
