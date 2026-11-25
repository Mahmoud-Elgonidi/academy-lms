<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
// The consent keeps its meaning (RecordingConsent::VERSION stays): sessions for lessons, the trainee for the family.
return [
    'room' => [
        'start' => 'Record the session',
        'may_record' => 'This session may be recorded; a "Recording" sign shows at the top while it runs.',
    ],
    'errors' => [
        'off' => 'The academy does not record sessions.',
        'not_now' => 'The session is not open now, so it cannot be recorded.',
        'no_room' => 'Nobody has joined the session yet. Join first, then start recording.',
        'outside_link' => 'This session is on an outside link (Zoom or Meet) and cannot be recorded from here.',
        'not_recorded' => 'This person is neither a trainer nor a trainee, so sessions do not record them.',
        'complaint' => 'There is an open complaint about this session, so its recording has to stay.',
    ],
    'settings' => [
        'section' => 'Session recordings',
        'section_hint' => 'A session is recorded only when its trainer and trainee agreed. Every time someone opens a recording is logged.',
        'fields' => [
            'recording_family' => 'Trainees watch their sessions\' recordings',
            'recording_teacher' => 'Trainers watch their sessions\' recordings',
        ],
        'modes' => [
            'manual' => 'The trainer or a supervisor starts it from the session page',
            'auto' => 'Every session records itself once the trainer and the trainee are in',
        ],
        'media' => [
            'by_age' => 'By age: video for anyone under 18, sound only for everyone else',
        ],
        'teacher_hint' => 'Trainers only watch; they never download.',
    ],
    'office' => [
        'nav' => 'Session recordings',
        'title' => 'Session recordings',
        'lead_off' => 'The academy does not record sessions now. Consents can be gathered already.',
        'columns' => [
            'lesson' => 'Session', 'student' => 'Trainee', 'teacher' => 'Trainer',
        ],
        'hold_hint' => 'It will not be removed by itself until released. Say why, such as a dispute with the trainee.',
        'remove_hint' => 'The file is removed for good; the record stays with the reason. Say why, such as the trainee asking for it.',
        'record_hint' => 'When you hold a written consent (a form signed by the trainee, the recording clause in a trainer\'s or client\'s contract), say what it is.',
        'withdraw_hint' => 'At their request. Any recording running in their sessions stops, and nothing more is recorded. Say who asked and when.',
        'empty_hint' => 'Recordings appear here once sessions are recorded.',
        'people_empty' => ['missing' => 'Every trainer and trainee has agreed'],
    ],
    'family' => [
        'nav' => ['classic' => 'Session recordings'],
        'title' => 'Session recordings',
        'off' => 'The academy does not record sessions now.',
        'list' => ':name\'s session recordings',
    ],
    'teacher' => [
        'nav' => 'My session recordings',
        'title' => 'My session recordings',
        'list' => 'Your sessions\' recordings',
        'empty' => 'No recordings of your sessions yet.',
        'not_shared' => 'The academy does not show session recordings to trainers.',
    ],
    'as' => ['teacher' => 'Trainer', 'family' => 'Trainee'],
    'roles' => ['teacher' => 'Trainer', 'student' => 'Trainee'],
    'consent' => [
        'title' => 'Consent to session recordings',
        'intro_self' => ':academy may record your sessions for follow-up and quality. Nothing is recorded unless you agree.',
        'sections' => [
            ['title' => 'What is recorded', 'body' => 'The sound, the picture when it records video, and anything shown on screen in the session. Everyone in the session sees a "Recording" sign while it runs.'],
            ['title' => 'Who watches it', 'body' => 'The office and supervisors, for follow-up; the trainer and the trainee when the academy allows it. Trainers only watch, never download. Every opening is logged with who and when.'],
            ['title' => 'How long it is kept', 'body' => 'It is removed by itself after :days days. If there is a complaint about the session, it stays until the complaint is closed.'],
            ['title' => 'You can change your mind', 'body' => 'You can withdraw consent at any time from the privacy page. Any recording running stops, and nothing more is recorded.'],
        ],
        'decides_self' => ':name decides themself whether their sessions are recorded.',
        'agree' => 'I agree to session recordings',
        'agreed' => 'Thank you. Sessions may be recorded from now on.',
        'off_note' => 'The academy does not record sessions now, but your consent is kept.',
    ],
];
