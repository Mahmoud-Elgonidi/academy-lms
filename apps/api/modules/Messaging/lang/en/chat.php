<?php

return [
    'sides' => ['teacher' => 'Teacher', 'student' => 'Student', 'guardian' => 'Guardian'],
    'kinds' => ['phone' => 'a phone number', 'email' => 'an email', 'link' => 'a link', 'handle' => 'an account name'],
    'and' => ' and ',
    'hidden' => 'We hid :what: conversations stay here on the platform.',
    'hidden_kids' => 'We hid :what: we only talk here 😊',
    'why_not' => [
        'off' => 'Messages are switched off at the academy for now.',
        'locked' => 'The academy has paused this conversation for now. You can read it, but not write.',
        'ended' => 'This teacher no longer teaches this student, so the conversation is read only.',
        'paused' => 'The academy has paused messages here for now. You can still read what was said.',
    ],
    'errors' => [
        'empty' => 'Write a message first.',
        'long' => 'That message is too long: :max characters at most.',
        'too_fast' => 'Slow down a little: wait a minute and send again.',
        'not_teaching' => 'You can only write to teachers who teach the student now.',
        'reason' => 'Write the reason (5 to 400 characters).',
        'locked_already' => 'This conversation is already paused.',
    ],
];
