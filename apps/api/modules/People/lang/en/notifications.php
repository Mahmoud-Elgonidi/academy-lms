<?php

return [
    'audience' => [
        'new_teacher' => 'The new teacher',
        'new_family' => 'The new guardian or student',
        'guardians' => 'The student\'s guardians',
        'office' => 'Who follows the families',
    ],
    'consent_request' => ['title' => 'We need your consent to :student\'s account', 'body' => 'For :student to sign in and join lessons on their own, we need your consent as their guardian. Read exactly what we do and agree here: :link'],
    'consent_withdrawn' => ['title' => ':guardian withdrew their consent to :student\'s account', 'body' => ':student was signed out and cannot sign in or join with their link until a guardian agrees again.'],
    'welcome_teacher' => ['title' => 'Welcome to :academy', 'body' => 'Hello :name, your teacher account at :academy is ready. Choose your password here and sign in: :link'],
    'welcome_family' => ['title' => 'Welcome to :academy', 'body' => 'Hello :name, your account at :academy is ready: lessons, reports and invoices in one place. Choose your password here and sign in: :link'],
];
