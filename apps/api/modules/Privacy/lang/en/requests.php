<?php

return [
    'kinds' => ['export' => 'A copy of the data', 'erasure' => 'Erase the account'],
    'statuses' => [
        'pending' => 'Waiting', 'ready' => 'Ready to download', 'expired' => 'Expired', 'failed' => 'Could not be built',
        'approved' => 'Approved', 'done' => 'Erased', 'refused' => 'Refused', 'cancelled' => 'Cancelled',
    ],
    'reason' => 'Reason',
    'errors' => [
        'not_yours' => 'You cannot ask this for that person.',
        'erased' => 'This account is already erased.',
        'export_recent' => 'You asked for a copy in the last 24 hours: download it here, or ask again tomorrow.',
        'not_open' => 'This request is already closed.',
        'answer' => 'Write the reason the person who asked will read.',
    ],
    'goes' => [
        'account' => 'The account: name, email, phone, password and ways in; every device is signed out',
        'profile' => 'Personal details: birth date, country, school year, and guardian links',
        'messages' => 'The messages and notifications they received, and their push devices',
        'homework' => 'The homework they handed in with its files, challenges and cheers',
        'ratings' => 'Their words in lesson ratings (the stars stay, without a name)',
        'enquiries' => 'The contact details on the enquiry they came through',
    ],
    'stays' => [
        'invoices' => 'Invoices, payments and their receipts: tax law requires keeping them',
        'lessons' => "Lessons, attendance and teachers' reports: the academy's record and teachers' pay",
        'pay' => "A teacher's pay: labour and tax law",
        'consents' => "The consents given and when: the academy's evidence",
        'tickets' => 'Support messages and complaints as they were said',
        'audit' => 'The audit log (without personal details)',
    ],
    'stays_note' => 'What stays, stays without a name: the account itself becomes "Deleted account".',
];
