<?php

// Settings › Security (the owner alone), and choosing a first password for someone who came in without one.
return [
    'nav' => 'Security',
    'title' => 'Security',
    'subtitle' => 'How the academy\'s team signs in to the back office.',
    'require' => 'Require two-step sign-in of the owner, admins and accountants',
    'require_hint' => 'Each of them sets up an authenticator app on their phone before opening the back office for the first time.',
    'why_title' => 'Why we recommend it',
    'why' => 'They see the payments, the payment gateways\' keys and every family\'s data. Whoever learns one of their passwords gets straight in unless two-step sign-in is on.',
    'off_title' => 'If you stop requiring it',
    'off' => 'The owner, admins and accountants sign in with their password or a code sent to them alone. Each of them can still turn two-step sign-in on for themselves in My settings, and whoever has it on is asked for it on every sign-in.',
    'saved' => 'Security settings saved.',
    'errors' => [
        'impersonating' => 'Security settings cannot be changed while the platform team is signed in as someone.',
    ],
    'password' => [
        'title' => 'Choose a password',
        'subtitle' => 'Your account has no password yet. Choose one first, then set up two-step sign-in.',
        'subtitle_plain' => 'Your account has no password yet. Choose one to sign in with from now on.',
        'password' => 'New password',
        'confirm' => 'Type it again',
        'hint' => ':min characters or more.',
        'save' => 'Save the password',
        'saved' => 'Password saved.',
        'choose' => 'Choose a password',
        'settings_hint' => 'You have not chosen a password for your account yet. Choose one first; then you can change it or turn on two-step sign-in here.',
        'errors' => [
            'has_one' => 'Your account already has a password. Change it in My settings.',
        ],
    ],
];
