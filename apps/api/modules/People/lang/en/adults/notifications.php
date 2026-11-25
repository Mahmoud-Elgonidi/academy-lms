<?php

return [
    'audience' => [
        'safeguarding' => 'The academy\'s safeguarding leads',
        'teacher' => 'The trainer',
        'teacher_and_safeguarding' => 'The trainer and the safeguarding leads',
        'new_teacher' => 'The new trainer',
        'new_family' => 'The new trainee',
        'office' => 'Who follows the trainees',
    ],
    'welcome_teacher' => ['body' => 'Hello :name, your trainer account at :academy is ready. Choose your password here and sign in: :link'],
    'welcome_family' => ['body' => 'Hello :name, your account at :academy is ready: your sessions, reports and invoices in one place. Choose your password here and sign in: :link'],
    'check_expiring' => ['body' => 'Send a new :kind before :until, so :teacher\'s file at the academy stays complete.'],
    'check_expired' => ['body' => 'Send a new :kind from My documents, so :teacher\'s file at the academy stays complete.'],
];
