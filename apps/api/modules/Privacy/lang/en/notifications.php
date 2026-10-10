<?php

return [
    'audience' => [
        'requester' => 'Whoever asked',
        'handlers' => 'Who answers data requests at the academy',
        'guardian' => "The guardian who asked to erase a child's account",
        'account' => 'The account holder, on all their devices and channels',
    ],
    'export_ready' => ['title' => 'The copy of the data is ready', 'body' => "The copy of :person's data can be downloaded from \"My data\" until :until."],
    'erasure_requested' => ['title' => 'An account erasure request', 'body' => "Someone asked to erase :person's account. It must be answered before :due."],
    'erasure_approved' => ['title' => 'The erasure was approved', 'body' => ":person's account will be erased :at. Changed your mind? Cancel it from \"My data\" before then."],
    'erasure_refused' => ['title' => 'The erasure was refused', 'body' => "The academy refused to erase :person's account: :answer"],
    'correction_requested' => ['title' => 'A data correction request', 'body' => "Someone asked for :person's data to be corrected. It needs an answer by :due."],
    'correction_done' => ['title' => 'The data was corrected', 'body' => "The academy corrected :person's data: :note", 'no_note' => 'You can check it on "My data".'],
    'correction_refused' => ['title' => 'The correction was refused', 'body' => "The academy did not correct :person's data: :answer"],
    'contact_changed' => [
        'title' => 'Your sign-in details changed',
        'body' => 'The :what on your account changed. If it was not you, contact the academy right away.',
        'email' => 'email', 'phone' => 'mobile number',
    ],
    'account_erased' => ['title' => 'The account was erased', 'body' => 'The account you asked to erase is gone. Invoices and lessons stayed, without a name, as the law requires.'],
];
