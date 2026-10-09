<?php

return [
    'audience' => [
        'requester' => 'Whoever asked',
        'handlers' => 'Who answers data requests at the academy',
        'guardian' => "The guardian who asked to erase a child's account",
    ],
    'export_ready' => ['title' => 'The copy of the data is ready', 'body' => "The copy of :person's data can be downloaded from \"My data\" until :until."],
    'erasure_requested' => ['title' => 'An account erasure request', 'body' => "Someone asked to erase :person's account. It must be answered before :due."],
    'erasure_approved' => ['title' => 'The erasure was approved', 'body' => ":person's account will be erased :at. Changed your mind? Cancel it from \"My data\" before then."],
    'erasure_refused' => ['title' => 'The erasure was refused', 'body' => "The academy refused to erase :person's account: :answer"],
    'account_erased' => ['title' => 'The account was erased', 'body' => 'The account you asked to erase is gone. Invoices and lessons stayed, without a name, as the law requires.'],
];
