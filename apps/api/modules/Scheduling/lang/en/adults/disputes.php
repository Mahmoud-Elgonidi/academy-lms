<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'title' => 'Session disputes',
    'sides' => ['family' => 'Trainee', 'teacher' => 'Trainer'],
    'reasons' => [
        'family' => [
            'teacher_absent' => 'The trainer did not come',
            'cut_short' => 'The session ended early',
            'student_attended' => 'I was there',
            'technical' => 'A technical problem not on my side',
        ],
        'teacher' => [
            'student_absent' => 'The trainee did not come',
        ],
    ],
    'outcomes' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'form' => [
        'heading' => 'Dispute this session\'s outcome',
        'description' => [
            'family' => 'The session is recorded as ":outcome". If that is not what happened, tell us within :hours hours of its end; the academy checks the session room\'s attendance and answers you.',
            'teacher' => 'The session is recorded as ":outcome". If that is not what happened, tell us within :hours hours of its end; the academy checks the session room\'s attendance and answers you.',
        ],
        'sent_body' => 'The session is under review; the answer comes here and in your notifications.',
    ],
    'page' => [
        'subtitle' => 'The trainee or the trainer can dispute a session\'s outcome within :hours hours of its end. The session waits for your decision; credit and pay follow it.',
        'roles' => ['teacher' => 'Trainer in the room', 'student' => 'Trainee in the room'],
        'calendar' => 'Session on the calendar',
    ],
    'decide' => [
        'heading' => 'Decide the dispute on :student\'s session',
        'context' => 'From the :side: :reason. The session is recorded as ":before".',
        'outcome' => 'What was the session after all?',
        'effects' => [
            'completed' => 'The minutes are used and the trainer is paid in full.',
            'teacher_absent' => 'The minutes go back to the trainee as makeup credit; the trainer is not paid.',
            'student_absent' => 'The minutes are used; the trainer gets the policy\'s share for an absence.',
            'cancelled_makeup' => 'The session is cancelled and the minutes come back as makeup credit.',
        ],
        'answer_hint' => 'They receive it and see it next to the session. Say briefly what you found.',
        'done' => ['upheld' => 'Dispute upheld; the session was corrected.', 'rejected' => 'The session stays as it was; the answer was sent.'],
        'short' => ':minutes minutes of the makeup had already been booked before the decision, so the trainee keeps them.',
    ],
    'errors' => [
        'reason' => 'Choose a reason that fits the session\'s outcome.',
        'closed' => 'A session can be disputed once it is decided, within :hours hours of its end.',
        'already' => 'This session has already been disputed.',
        'not_yours' => 'You cannot dispute this session.',
    ],
];
