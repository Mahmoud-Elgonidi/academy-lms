<?php

// The Quran module's messages (Listeners\SendQuranNotifications).
return [
    'audience' => [
        'examiners' => 'Who examines the Quran at the academy',
        'family' => 'The student and their guardians',
        'examiner' => 'The exam\'s examiner',
    ],
    'exam_suggested' => ['title' => 'A Quran exam is due: :student', 'body' => ':student reached :stop. Book the exam with an examiner.'],
    'exam_booked' => ['title' => ':student\'s Quran exam is booked', 'body' => 'The exam of :stop with :examiner, :when.'],
    'exam_assigned' => ['title' => 'You examine :student', 'body' => 'The exam of :stop, :when.'],
    'exam_passed' => ['title' => ':student passed the exam of :stop', 'body' => 'Grade: :grade. Well done!'],
    'exam_failed' => ['title' => ':student will take the exam of :stop again', 'body' => 'Grade: :grade. The examiner\'s advice is in the app; the retake can be from :day.'],
    'certificate_issued' => ['title' => ':student\'s certificate is ready', 'body' => 'The certificate for :stop (:number). Open and print it: :link'],
];
