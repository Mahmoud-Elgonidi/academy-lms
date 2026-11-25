<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../discounts.php.
// The sibling discount is the multi-trainee discount (one payer, several trainees).
return [
    'payer' => [
        'family_invoice' => 'This invoice is for more than one programme, so a coupon cannot be added here. Message the academy to put it on the right programme.',
        'not_block' => 'A coupon does not go on an invoice for extra sessions; only the multi-trainee discount does.',
        'offer_stays' => 'This is your offer after the free introductory session; it stays so you do not lose it. If you have another coupon, message the academy.',
    ],
    'payer_errors' => [
        'used_before' => 'You have used coupon :code before; it is once per payer.',
        'cancelled' => 'This programme is no longer on, so a coupon cannot be added.',
    ],
    'quote' => [
        'sibling' => 'Multi-trainee discount (:rate)',
        'late_join' => 'Sessions missed before joining (:count)',
        'sibling_bigger' => 'The multi-trainee discount is bigger, so it applies now; the coupon waits for a later invoice.',
    ],
    'errors' => [
        'personal' => 'This is a trainee\'s own offer after their free introductory session, made by the academy\'s rules, so it cannot be edited. Change the offer after a trial in the lead settings.',
        'has_coupon' => 'This programme already has a coupon. Take it off first.',
        'no_coupon' => 'This programme has no coupon.',
        'extra_count' => 'From 1 to :max sessions.',
        'extra_inactive' => 'Extra sessions are for a programme that is on and paid.',
        'extra_waiting' => 'Invoice :number for extra sessions is waiting to be paid.',
        'no_subscription' => 'This programme has no subscription.',
    ],
    'coupons' => [
        'subtitle' => 'Codes you give out that take a percentage or an amount off a programme\'s invoices. Each payer can use a coupon once.',
        'fields' => [
            'code_hint' => 'What the trainee or the salesperson types: RAMADAN10',
            'blocks_hint' => 'Empty: every invoice while the programme lasts. 1: the first invoice only.',
            'with_sibling' => 'On top of the multi-trainee discount',
            'with_sibling_hint' => 'Off: a payer who has the multi-trainee discount gets the bigger of the two.',
        ],
    ],
    'sibling' => [
        'action' => 'Several trainees',
        'title' => 'Several trainees: discount and combined invoice',
        'hint' => 'For a payer paying for more than one trainee: the first trainee pays in full, then each trainee after gets this off their invoices. 0 turns it off. Invoices already issued stay as they are.',
        'second' => 'Second trainee',
        'more' => 'Third trainee and after',
        'saved' => 'Multi-trainee settings saved.',
        'family_days' => 'One combined invoice per payer',
        'family_days_hint' => 'When a trainee\'s renewal goes out, the renewals of the payer\'s other trainees due within this many days go on the same invoice. 0 keeps each trainee\'s on its own.',
    ],
    'course' => [
        'coupon_title' => 'A coupon on this programme',
        'coupon_now' => 'Coupon :code (:value) is on this programme.',
        'applied' => 'Coupon :code is on this programme.',
        'applied_reissued' => 'Coupon :code is on this programme. Invoice :number replaces the one waiting.',
        'extra_action' => 'Extra sessions',
        'extra_title' => 'Sell extra sessions',
        'extra_hint' => 'An invoice for extra sessions at :price a session (:minutes min). When it is paid, their minutes are added to this programme; then book them from "Book an extra session".',
        'extra_waiting' => 'Invoice :number for extra sessions is waiting to be paid.',
        'lessons' => 'Sessions',
    ],
    'family' => [
        'extra' => 'Extra sessions',
        'extra_hint' => 'Need more sessions than the plan? Each extra session is :price (:minutes min).',
        'extra_title' => 'Extra sessions for :subject',
        'extra_body' => 'Each session is :price; once you pay, the academy books them with you.',
        'extra_waiting' => 'Invoice :number for extra sessions is waiting to be paid.',
    ],
];
