<?php

// Quran exams (Actions\Exams\*).
return [
    'stops' => [
        'surah' => 'Surah :name',
        'juz' => 'Part :number',
        'program' => 'The program “:name”',
        'khatma' => 'The whole Quran',
    ],
    'statuses' => [
        'suggested' => 'Waiting to be booked',
        'booked' => 'Booked',
        'passed' => 'Passed',
        'failed' => 'To try again',
        'cancelled' => 'Cancelled',
    ],
    'attributes' => [
        'mistakes' => 'mistakes', 'grade' => 'grade', 'note' => 'note', 'retake_on' => 'retake day', 'reason' => 'reason',
        'examiner_id' => 'examiner', 'starts_at' => 'time', 'duration_min' => 'length',
    ],
    'errors' => [
        'off' => 'The academy does not hold Quran exams now (Quran settings).',
        'stop' => 'An exam is at the end of a part (1–30), a surah (1–114), or the whole program.',
        'not_suggested' => 'Only an exam waiting to be booked can be booked.',
        'not_booked' => 'This exam is not booked, or its result is already recorded.',
        'not_open' => 'This exam is already over or cancelled.',
        'no_course' => 'This exam has no course to book it on.',
        'not_now' => 'The result is recorded from the start of the exam lesson, unless it was cancelled.',
        'not_examiner' => 'Only the exam\'s examiner records its result.',
        'before_retake' => 'This retake is not booked before :day.',
        'examiner_own_teacher' => 'The examiner cannot be the student\'s own teacher.',
        'examiner_not_examiner' => 'This person does not examine the Quran at this academy.',
        'examiner_student' => 'The student cannot examine themself.',
        'fail_note' => 'Say what to work on before trying again.',
        'fail_retake_on' => 'Choose the day to try again: after today and within :days days.',
    ],
];
