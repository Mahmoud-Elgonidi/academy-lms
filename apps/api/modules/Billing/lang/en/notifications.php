<?php

return [
    'audience' => ['payer' => 'Whoever pays (guardian or student)', 'accounts' => 'Whoever records payments (owner, admin, accountant)'],
    'invoice_issued' => [
        'title' => 'New invoice :number',
        'body' => 'Invoice for :student of :total, due by :due. The invoice and ways to pay: :link',
    ],
    'invoice_paid' => [
        'title' => 'Payment received, thank you',
        'body' => 'Invoice :number is paid in full (:total) and the lessons are confirmed.',
    ],
    'proof_received' => [
        'title' => 'Proof of payment to review',
        'body' => ':payer sent proof of paying :amount by :method on invoice :number (:student). Check it arrived, then approve it or turn it down.',
    ],
    'proof_approved' => [
        'title' => 'Your payment is confirmed',
        'body' => 'We received :amount on invoice :number. Left to pay: :left.',
    ],
    'proof_rejected' => [
        'title' => 'We could not confirm your payment',
        'body' => 'Your proof of :amount on invoice :number was turned down: :reason. You can send another: :link',
    ],
];
