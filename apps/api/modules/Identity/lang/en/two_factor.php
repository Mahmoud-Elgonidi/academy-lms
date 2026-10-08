<?php

// Two-step sign-in: an authenticator app, with one-time recovery codes.
return [
    'errors' => [
        'impersonating' => 'Two-step sign-in cannot be changed while the platform team is signed in as this person.',
        'start_again' => 'That took too long. Start again.',
        'expired' => 'This sign-in took too long. Sign in again.',
        'wrong' => 'That code is not right. Use the code your authenticator app shows now, or a recovery code.',
        'reason' => 'Say why, in at least :min characters.',
        'not_on' => 'Two-step sign-in is not on for this person.',
    ],
    'api' => [
        'required' => 'Two-step sign-in is on for this account: send the code from the authenticator app (or a recovery code) to auth/two-factor.',
        'setup_required' => 'This account must set up two-step sign-in in the back office before signing in here.',
    ],
    'console' => [
        'on' => 'Two-step sign-in is on',
        'reset' => 'Reset two-step sign-in',
        'reset_hint' => 'For someone who lost their phone and their recovery codes: they set it up again at their next sign-in. Write why in the reason above.',
        'reset_done' => 'Two-step sign-in reset; they set it up again at their next sign-in.',
    ],
];
