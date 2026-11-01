<?php

// The family's report after a free trial, and one more trial from it (docs/architecture/02, section 27, J2).
return [
    'offer_coupon_name' => 'Offer after the free trial',
    'errors' => [
        'link_expired' => 'This link has ended. Sign in to your account to find the report, or message the academy on WhatsApp and we will send you a new link.',
        'rebooked' => 'You have booked a new time already: its time and the link to join are in our latest message.',
    ],
    'next' => [
        'why' => [
            'family_only' => 'A parent books the free trial from their own account.',
            'second_off' => 'The academy does not offer a second free trial at the moment. Message us and we will help you choose the right teacher.',
            'not_assessed' => 'The second trial can be booked once the teacher has written the first one\'s report, which we will send you soon.',
            'no_number' => 'We no longer have your number. Message the academy on WhatsApp and we will book it for you.',
            'already_student' => 'The student has started this subject with us. To change the teacher or the time, message us from your account and we will arrange it.',
            'second_used' => 'You have already booked the second free trial in this subject. Message us and we will help you choose the right teacher.',
            'second_late' => 'More than two weeks have passed since the free trial, so the free second trial has ended. Message us and we will help you choose the right teacher.',
            'had_trial' => 'The student took their free trial in this subject. Read its report, or message us and we will help you choose a plan.',
            'missed_twice' => 'The student missed two free trials in this subject. Message us and we will agree a time that suits you.',
            'trial_open' => 'This student already has a free trial booked in this subject, and its time is in our message. You can change the time from the link in it.',
            'too_many_open' => 'This number has :count free trials booked that have not taken place yet. Wait until one of them has, or message us and we will book it for you.',
            'staff_books' => 'The academy\'s team books free trials. Message the academy on WhatsApp and we will book a time that suits you.',
        ],
    ],
];
