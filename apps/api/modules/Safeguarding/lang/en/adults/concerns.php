<?php

// An academy that trains adults only (docs/architecture/02, section 29): concerns about safety and conduct among adults.
return [
    'categories' => [
        'classic' => [
            'adult_behaviour' => 'A trainer or someone else said or did something that is not OK',
            'bullying' => 'Someone was abused, harassed or upset',
            'wellbeing' => 'Someone is not OK or not safe',
        ],
        'teacher' => [
            'wellbeing' => 'A trainee seems not OK or not safe',
            'outside_contact' => 'A trainee asked to talk off the platform, or sent something unsuitable',
            'adult_behaviour' => 'Something someone else did worries me',
            'content' => 'Something unsuitable was shown or shared in a session',
        ],
    ],
    'outcomes' => [
        'teacher_action' => 'Action taken with a trainer',
    ],
    'sides' => ['student' => 'The trainee', 'teacher' => 'A trainer'],
    'fields' => [
        'lesson' => 'In which session (if any)?',
        'student' => 'Which trainee?',
        'teacher' => 'The trainer it may be about',
    ],
    'errors' => [
        'lesson' => 'That session cannot be linked here.',
        'student' => 'Choose one of the trainees you train.',
        'teacher' => 'Choose one of the academy\'s trainers.',
        'no_teacher' => 'This concern does not name a trainer yet: set one first.',
    ],
    'privacy' => ['open' => 'A concern raised by them or about them is still open.'],
    'thanks' => 'Thank you for telling us. Only the people who look after safety at the academy will read this.',
];
