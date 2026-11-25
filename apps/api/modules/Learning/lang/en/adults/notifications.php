<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// No guardians there, so what went to "the student and their guardians" reaches the trainee alone: written to them.
return [
    'audience' => [
        'family' => 'The trainee',
        'student' => 'The trainee',
        'teacher' => 'The trainer',
        'guardian' => 'Nobody (an adults academy has no guardians)',
    ],
    'report_submitted' => [
        'title' => 'Your session report',
        'body' => ':teacher wrote the report on the session of :when. Rated :overall out of 5. :note',
    ],
    'homework_assigned' => [
        'title' => 'A new task for you',
        'body' => ':title. Hand it in by :due.',
    ],
    'homework_reviewed' => [
        'title' => 'Your task was marked',
        'body' => ':title: :grade out of 5. :feedback',
    ],
    'node_mastered' => [
        'title' => 'Well done! You mastered a new lesson',
        'body' => 'You mastered ":lesson"; :percent% of :program done.',
    ],
    'homework_submitted' => [
        'title' => ':student handed in a task',
    ],
    'report_overdue' => [
        'title' => 'Session report for :student is overdue',
        'body' => 'The session was on :when. Write it now so the trainee knows where things stand.',
    ],
    'lesson_stars' => [
        'title' => ':stars from your :subject session',
    ],
];
