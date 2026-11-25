<?php

// An academy that trains adults only: safety and conduct instead of keeping children safe, no rule for under-18s.
return [
    'office' => [
        'nav' => 'Safety and conduct',
        'title' => 'Safety and conduct',
        'lead' => 'Concerns about safety and conduct in sessions and on the platform. Only the leads see this page: take a new one within the hour.',
        'private' => 'Only the safety leads see this. Do not share it beyond them.',
        'actions' => [
            'reply_hint' => 'Optional. Keep it general: never name anyone else or say what was done about a trainer.',
        ],
    ],
    'family' => [
        'title' => ['classic' => 'Report something that worries you'],
        'lead' => [
            'classic' => 'If anything about a session, a trainer or anyone on the platform worries you, tell us here. Only the people who look after safety at the academy read it.',
        ],
        'sent' => ['classic' => 'Thank you for telling us. The academy\'s safety lead will look at it.'],
    ],
    'teacher' => [
        'title' => 'Raise a concern about safety or conduct',
        'lead' => 'If something about a trainee\'s safety or wellbeing worries you, or someone else\'s behaviour, tell the academy\'s safety leads here. Only they read it. Do not look into it yourself.',
        'danger' => 'If someone is in danger right now, call the emergency services first, then write it here.',
        'student' => 'Which trainee?',
        'no_student' => 'Not about one trainee',
        'send' => 'Send to the safety leads',
        'sent' => 'Thank you. The safety leads have it.',
    ],
    'form' => [
        'lesson' => 'In which session (if any)?',
        'no_lesson' => 'Not about a session',
    ],
];
