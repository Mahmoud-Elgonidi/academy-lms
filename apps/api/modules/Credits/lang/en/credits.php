<?php

return [
    'sources' => [
        'purchase' => 'Paid',
        'makeup' => 'Makeup',
        'trial' => 'Trial',
        'bonus' => 'Bonus',
        'adjustment' => 'Adjustment',
    ],
    'entries' => [
        'grant' => 'Added',
        'reserve' => 'Reserved for a session',
        'release' => 'Back from a session',
        'consume' => 'Session used',
        'expire' => 'Expired',
        'restore' => 'Back after a review',
        'reclaim' => 'Taken back after a review',
        'extend' => 'Validity extended',
    ],
    'release_modes' => [
        'refund' => 'back to the balance',
        'makeup' => 'turned into makeup credit',
    ],
    'minutes' => ':n min',
];
