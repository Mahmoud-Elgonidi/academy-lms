<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'kind_hints' => [
        'complaint' => 'About a trainer or a session. The academy looks into it and tells you what it did.',
        'technical' => 'The session room, the app or signing in not working.',
    ],
    'lesson_statuses' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'attributes' => [
        'learner' => 'trainee',
        'session_id' => 'session',
        'teacher_id' => 'trainer',
    ],
    'errors' => [
        'learner' => 'Choose the trainee.',
        'lesson' => 'Choose one of your sessions from the last two months.',
        'teacher' => 'Choose one of the academy\'s trainers.',
        'teacher_needed' => 'Choose the trainer this complaint is about before confirming it.',
        'teacher_complaint' => 'A trainer\'s ticket cannot be a complaint about a trainer.',
    ],
    'queue' => [
        'subtitle' => 'What trainees and trainers wrote to the academy. Answer the late ones first; a complaint is confirmed or dismissed by whoever oversees the trainers.',
        'teacher' => 'Trainer: :name',
        'from_teacher' => 'Trainer',
        'new_hint' => 'A trainee or a trainer told you on the phone or in person: write it down for them, so they follow the answer in their account.',
        'for_family' => 'A trainee',
        'for_teacher' => 'A trainer',
        'learner' => 'Trainee',
        'teacher_name' => 'Trainer',
        'teacher_about' => 'Trainer it is about',
        'teacher_about_hint' => 'When no session says who.',
    ],
    'page' => [
        'learner' => 'Trainee',
        'lesson' => 'Session',
        'no_lesson' => 'No session',
        'from_family' => 'Trainee',
        'from_teacher' => 'Trainer',
        'complaint_no_teacher' => 'No trainer named yet',
        'complaint_pending' => 'Look into it, then confirm it (it counts in the trainer\'s complaints) or dismiss it. The trainee never sees your reason.',
        'confirm_hint' => 'It counts in the trainer\'s complaints for 90 days, and they hear that a complaint about their session was confirmed (without the trainee\'s name).',
        'dismiss_hint' => 'It does not count against the trainer.',
    ],
    'mine' => [
        'subtitle' => 'Write to the academy about a session, a payment, a technical problem or anything else. You get the answers here and in a message.',
        'lesson' => 'Which session? (if any)',
        'no_lesson' => 'No particular session',
    ],
];
