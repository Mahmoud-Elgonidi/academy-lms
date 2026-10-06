<?php

return [
    'audience' => [
        'family' => 'The student and their guardians',
        'teacher' => 'The teacher',
    ],
    'report_submitted' => [
        'title' => 'Lesson report for :student',
        'body' => ':teacher wrote the report on the lesson of :when. Rated :overall out of 5. :note',
    ],
    'homework_assigned' => [
        'title' => 'New homework for :student',
        'body' => ':title. Hand it in by :due.',
    ],
    'homework_reviewed' => [
        'title' => ':student\'s homework was marked',
        'body' => ':title: :grade out of 5. :feedback',
    ],
    'node_mastered' => [
        'title' => 'Well done! :student mastered a new lesson',
        'body' => 'Mastered ":lesson"; :percent% of :program done.',
    ],
    'homework_submitted' => [
        'title' => ':student handed in homework',
        'body' => '":title" is waiting for your marking.',
    ],
    'report_overdue' => [
        'title' => 'Lesson report for :student is overdue',
        'body' => 'The lesson was on :when. Write it now so the family knows where things stand.',
    ],
    'no_grade' => 'no grade',
];
