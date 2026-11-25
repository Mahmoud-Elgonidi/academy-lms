<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'kpis' => [
        'absences_hint' => ':teacher trainer · :student trainee',
    ],
    'cols' => [
        'teacher' => 'Trainer',
        'student' => 'Trainee',
        'subject' => 'Area',
    ],
    'status' => [
        'late' => 'Trainer late',
        'waiting' => 'Waiting for trainee',
    ],
    'status_names' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'checks' => [
        'teacher_free' => 'Trainer free',
        'teacher_busy' => 'Trainer in a session before',
    ],
    'issues' => [
        'teacher_not_joined' => [
            'context' => ':base · trainee waiting since :time',
            'rule' => 'trainer absent + makeup session for :student',
        ],
        'student_not_joined' => [
            'context' => ':base · trainer waiting since :time',
            'rule' => 'trainee absent, the session is charged',
        ],
        'weak_connection' => [
            'rule' => 'Under 75% delivered: the session is reviewed, nobody is charged unfairly',
        ],
    ],
    'review_reasons' => [
        'short_delivery' => 'under 75% delivered',
    ],
    'results' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'clocks' => [
        'student' => 'Trainee',
        'teacher' => 'Trainer',
    ],
    'detail' => [
        'people' => 'Payer and package',
    ],
    'relationships' => [
        'parent' => 'Payer',
        'father' => 'Contact',
        'mother' => 'Contact',
        'guardian' => 'Payer',
    ],
    'targets' => [
        'teacher' => 'the trainer',
        'student' => 'the trainee',
        'guardian' => 'the payer',
    ],
    'actions' => [
        'call_teacher' => 'Call trainer',
        'call_guardian' => 'Call payer',
        'call_student' => 'Call trainee',
        'reassure' => 'Reassure trainee',
        'mark_student_absent' => 'Mark trainee absent',
        'substitute' => 'Substitute trainer',
    ],
    'hints' => [
        'reassure' => 'Tell the trainee the trainer is on the way',
        'mark_student_absent' => 'Closes as trainee absent: charged to the package, the trainer is paid',
        'dispute' => 'The session goes back under review; credit and pay follow the decision',
        'observe' => 'Opens the session in a new tab; nobody sees or hears you.',
        'resend_link' => 'Sends :student the join link again, even in quiet hours.',
        'soon_classroom' => 'Arrives with the live session room',
        'soon_substitute' => 'Arrives with trainer search',
    ],
    'cancel' => [
        'makeup' => 'Trainee gets a makeup session',
    ],
    'cancel_reasons' => [
        'teacher_absent' => 'Trainer absent',
        'student_request' => 'Trainee asked',
    ],
    'resolve' => [
        'outcomes' => [
            'completed' => 'Completed (charged, trainer paid)',
            'teacher_absent' => 'Trainer absent (makeup for the trainee)',
            'student_absent' => 'Trainee absent (charged)',
        ],
    ],
    'done' => [
        'excused' => 'The trainee\'s seat is excused; the session goes on for the rest of the group.',
        'student_absent' => 'Trainee marked absent.',
    ],
    'log' => [
        'joined_teacher' => 'Trainer joined',
        'joined_student' => 'Trainee joined',
        'left' => 'Left the session room',
        'decision_student_added' => 'A trainee joined the session',
        'decision_student_removed' => 'A trainee left the session',
        'teacher_changed' => ':actor moved it to another trainer',
    ],
    'errors' => [
        'double_booked' => 'The trainer or the trainee already has a session then.',
        'not_student_absent' => 'A trainee is marked absent only when the trainer is in and the trainee is not.',
        'extend_overlap' => 'The trainer or the trainee has a session right after.',
        'changed' => 'Someone else changed this session just now. Look at it again, then try.',
    ],
];
