<?php

return [
    'lines' => [
        'block' => ':subject — :plan (block :block)',
        'extra' => ':subject — extra lessons (:minutes min each)',
        'sibling' => 'Sibling discount (:rate)',
        'coupon' => 'Coupon :code (:value)',
        'shared' => 'Shared package',
    ],
    'statuses' => [
        'draft' => 'Draft',
        'issued' => 'Due',
        'paid' => 'Paid',
        'void' => 'Void',
        'overdue' => 'Overdue',
        'partly_paid' => 'Partly paid',
    ],
    'methods' => [
        'bank_transfer' => 'Bank transfer',
        'instapay' => 'InstaPay',
        'wallet' => 'Mobile wallet',
        'cash' => 'Cash',
        'card_terminal' => 'Card (terminal)',
        'other' => 'Other',
        'online' => 'Online',
    ],
    'subscription_statuses' => [
        'active' => 'Active',
        'past_due' => 'Past due',
        'paused' => 'Paused',
        'cancelled' => 'Cancelled',
    ],
    // Why a block invoice waiting to be paid was voided when its course ended (on the invoice and its credit note).
    'void_course_ended' => 'The course ended',
    'errors' => [
        'no_price' => 'This plan has no price in this region. Add one under "Plans and prices".',
        'method' => 'Choose how it was paid.',
        'not_payable' => 'This invoice is not due, so no payment can be recorded on it.',
        'currency' => 'The invoice is in :currency.',
        'amount' => 'The amount must be above zero and at most what is left (:max).',
        'not_voidable' => 'Only a due invoice with nothing paid on it can be voided.',
        'void_proof' => 'A transfer receipt is waiting on this invoice: approve it or turn it down first.',
        'void_checkout' => 'An online payment is under way on this invoice: wait until it finishes or runs out.',
    ],
];
