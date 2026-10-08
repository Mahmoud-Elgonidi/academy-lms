<?php

return [
    'validation' => 'The given data was invalid.',
    'unauthenticated' => 'You need to sign in.',
    'forbidden' => 'You are not allowed to do this.',
    'two-factor-required' => 'Enter the code from your authenticator app.',
    'guardian-consent-required' => 'This account is waiting for a guardian\'s consent.',
    'two-factor-setup-required' => 'Set up two-step sign-in first.',
    'plan-limit-reached' => "The academy's plan does not allow more.",
    'feature-not-in-plan' => "This is not in the academy's plan.",
    'academy-suspended' => 'This academy is suspended.',
    'academy-not-found' => 'Academy not found.',
    'not-found' => 'Not found.',
    'bad-request' => 'Bad request.',
    'method-not-allowed' => 'Method not allowed.',
    'conflict' => 'This conflicts with the current state.',
    'too-many-requests' => 'Too many requests. Try again shortly.',
    'http-error' => 'The request could not be completed.',
    'idempotency_key' => 'This Idempotency-Key is too long.',
    'idempotency_reused' => 'This Idempotency-Key was already used for a different request.',
    'server-error' => 'Something went wrong on our side.',
    // The web pages of a suspended academy (the API answers academy-suspended).
    'suspended_page' => ['title' => 'This academy is paused', 'body' => 'This site is not available right now. If you belong to the academy, please contact its office.'],
];
