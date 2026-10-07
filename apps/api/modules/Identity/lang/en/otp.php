<?php

return [
    'contact_needed' => 'Enter your email or mobile number to get a code.',
    'international' => 'Enter an email, or a mobile number with its country code (+966…).',
    'channel_unavailable' => 'A code cannot be sent that way.',
    'no_channel' => 'Codes cannot be sent right now. Sign in with your password.',
    'too_soon' => 'Please wait :seconds seconds before asking for another code.',
    'wrong' => '{1} That code is not right. One try left.|[2,*] That code is not right. :left tries left.',
    'locked' => 'Too many wrong codes. Ask for a new one.',
    'expired' => 'This code has expired. Ask for a new one.',
    'discovery_expired' => 'Your check has expired. Ask for a new code.',
    'not_yours' => 'This account does not use the email or number you verified.',
    'sign_in' => [
        'use_code' => 'Sign in with a code instead',
        'use_password' => 'Sign in with my password',
        'contact' => 'Email or mobile number',
        'send' => 'Send me a code',
        'code' => 'Code',
        'verify' => 'Sign in',
        'sent' => 'If :contact belongs to an account here, a code is on its way by :channel.',
        'resend' => 'Send a new code',
        'resend_by' => 'Send by :channel instead',
        'resend_in' => 'You can ask for a new code in :seconds s.',
        'change' => 'Use another email or number',
        'channels' => ['whatsapp' => 'WhatsApp', 'sms' => 'text message', 'email' => 'email'],
    ],
];
