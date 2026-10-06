<?php

return [
    'audience' => ['payer' => 'Whoever pays (guardian or student)'],
    'invoice_issued' => [
        'title' => 'New invoice :number',
        'body' => 'Invoice for :student of :total, due by :due.',
    ],
    'invoice_paid' => [
        'title' => 'Payment received, thank you',
        'body' => 'Invoice :number is paid in full (:total) and the lessons are confirmed.',
    ],
];
