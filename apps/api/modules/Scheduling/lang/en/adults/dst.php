<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../dst.php.
return [
    'subtitle' => 'Sessions whose time moves for the trainee in the next :days days, because the clocks change on their side or the trainer\'s. By default the session keeps the trainer\'s time, the trainee\'s time moves, and the trainee is told 14 days and 2 days before. To keep the trainee\'s time instead, choose "Keep the trainee\'s time": the trainer\'s time moves for that period, if they are free.',
    'empty' => 'No session times move in the next :days days.',
    'student_side' => 'Trainee (:zone)',
    'teacher_side' => 'Trainer (:zone)',
    'lessons' => '{0} no booked sessions|{1} one booked session|[2,*] :count booked sessions',
    'decisions' => [
        'pending' => 'Default: trainer\'s time',
        'keep_teacher' => 'Trainer\'s time kept',
        'keep_student' => 'Trainee\'s time kept',
    ],
    'told' => 'Trainee told',
    'will_tell' => 'Trainee told on :date',
    'possible' => 'The trainer is free to keep the trainee\'s time',
    'impossible' => 'The trainer is not free for :count of the sessions at the trainee\'s time',
    'keep_teacher' => 'Keep the trainer\'s time',
    'keep_student' => 'Keep the trainee\'s time',
    'confirm_student' => 'To keep :student at :time, :zone time, their sessions with :teacher will be at :teacher_time on the trainer\'s clock from :from, :until. The trainer is told, and the trainee too if they had been told of the change.',
    'confirm_bulk' => 'The trainee\'s time will be kept for :count. Where the trainer is not free, nothing changes.',

    'shift' => [
        'button' => 'Move a trainer\'s sessions for a period',
        'heading' => 'Move a trainer\'s sessions for a period',
        'hint' => 'When the academy\'s or the trainer\'s hours change for a while (Ramadan, say): every weekly session of the trainer in that period moves by the same amount on their clock, and returns to its time after it. The trainer must be on shift and free, and the trainee free, for every session.',
        'teacher' => 'Trainer',
        'preview_none' => 'This trainer has no booked sessions in that period.',
        'preview_ok' => ':lessons for :students will move, and each trainee is told their new time.',
        'preview_busy' => 'Not possible: for :count of the sessions the trainer is off shift or busy, or the trainee has another session (:dates).',
        'students' => '{1} one trainee|[2,*] :count trainees',
        'apply' => 'Move the sessions',
    ],

    'family' => [
        'notice' => 'From :date, the session will be at :after instead of :before, :zone time, because the clocks change in :cause.',
    ],

    'errors' => [
        'busy' => 'The trainer is off shift or busy, or the trainee has another session, on: :dates',
    ],
];
