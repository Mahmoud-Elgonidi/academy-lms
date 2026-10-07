<?php

return [
    // The last line lets the browser offer the code on the academy's sign-in page (WebOTP).
    'sms' => ':code is your :name sign-in code. It works for :minutes minutes. Never share it.',
    'mail' => [
        'subject' => ':code is your :name sign-in code',
        'heading' => 'Your sign-in code',
        'body' => 'Type this code where you asked for it. It works for :minutes minutes.',
        'never' => 'Nobody from :name will ask you for this code. If you did not ask for it, ignore this email.',
    ],
];
