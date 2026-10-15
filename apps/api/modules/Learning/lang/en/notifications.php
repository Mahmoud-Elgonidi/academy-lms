<?php

return [
    'audience' => [
        'family' => 'The student and their guardians',
        'student' => 'The student',
        'teacher' => 'The teacher',
        'guardian' => 'The student\'s guardians who receive reports',
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
    'cheer' => [
        'title' => ':from is cheering you on!',
        'body' => ':from sent you :kind. Keep going!',
    ],
    'lesson_stars' => [
        'title' => ':stars from your :subject lesson',
        'body' => ':how. Your stars are on your journey!',
    ],
    // The weekly note to guardians (Actions\Digest\SendWeeklyDigest): :body is the week, written already.
    'weekly_digest' => ['title' => ":student's week", 'body' => ':body'],
    'weekly_digest_voice' => ['title' => ":student's week", 'body' => ':body Hear :student recite from this week: :link'],
];
