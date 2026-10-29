<?php

// Signed in with an account a panel does not take (App\Http\Middleware\AuthenticateForPanel).
return [
    'areas' => [
        'academy' => "the academy's back office",
        'teach' => "the teacher's area",
        'family' => "the students' and guardians' area",
    ],
    'wrong' => [
        'title' => 'This account does not open :area',
        'body' => 'You are signed in as :name, who cannot open :area. Sign out and sign in with the right account.',
        'sign_out' => 'Sign out and use another account',
        'own' => 'Go to :area',
    ],
];
