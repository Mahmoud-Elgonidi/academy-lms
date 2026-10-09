<?php

return [
    'audience' => [
        'safeguarding' => 'The academy\'s safeguarding leads',
        'teacher' => 'The teacher',
        'teacher_and_safeguarding' => 'The teacher and the safeguarding leads',
        'new_teacher' => 'The new teacher',
        'new_family' => 'The new guardian or student',
        'guardians' => 'The student\'s guardians',
        'office' => 'Who follows the families',
    ],
    'consent_request' => ['title' => 'We need your consent to :student\'s account', 'body' => 'For :student to sign in and join lessons on their own, we need your consent as their guardian. Read exactly what we do and agree here: :link'],
    'consent_withdrawn' => ['title' => ':guardian withdrew their consent to :student\'s account', 'body' => ':student was signed out and cannot sign in or join with their link until a guardian agrees again.'],
    'welcome_teacher' => ['title' => 'Welcome to :academy', 'body' => 'Hello :name, your teacher account at :academy is ready. Choose your password here and sign in: :link'],
    'welcome_family' => ['title' => 'Welcome to :academy', 'body' => 'Hello :name, your account at :academy is ready: lessons, reports and invoices in one place. Choose your password here and sign in: :link'],
    'check_submitted' => ['title' => ':teacher sent a document to check', 'body' => ':teacher sent their :kind. Check it against the original and verify it or turn it down.'],
    'check_verified' => ['title' => 'Your :kind was verified', 'body' => 'The academy verified your :kind; it is valid until :until.'],
    'check_rejected' => ['title' => 'Your :kind needs another look', 'body' => 'The academy could not accept your :kind: :reason. Send it again from My documents.'],
    'check_expiring' => ['title' => ':teacher\'s :kind runs out on :until', 'body' => 'Send a new :kind before :until; after that, no new lessons with students under 18 can be booked with :teacher when the academy asks for a check.'],
    'check_expired' => ['title' => ':teacher\'s :kind ran out on :until', 'body' => 'Until a new :kind is verified, no new lessons with students under 18 can be booked with :teacher when the academy asks for a check.'],
];
