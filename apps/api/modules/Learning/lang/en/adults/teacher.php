<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../teacher.php.
return [
    'students' => [
        'nav' => 'My trainees',
        'title' => 'My trainees',
        'to_mark' => '{1} One task to mark|[2,*] :n tasks to mark',
        'none' => 'No trainees enrolled with you yet.',
    ],
    'nav_homework' => 'Tasks',
    'reports' => [
        'subtitle' => 'Write each session\'s report within :hours hours of its end. It starts from today\'s plan in the trainee\'s programme.',
        'student' => 'Trainee',
        'lesson' => 'Session',
    ],
    'write' => [
        'title_lesson' => 'Session report · :student',
        'no_program' => 'This trainee follows no programme, so the report is notes and scores only.',
        'notes_for_student' => 'Notes for the trainee',
        'absence_hint' => 'E.g. called twice with no answer, or the trainee said they were ill.',
        'homework' => 'Task (optional)',
        'homework_title' => 'Task',
        'roadmap' => 'The trainee\'s programme',
    ],
    'homework' => [
        'title' => 'Tasks',
        'subtitle' => 'Tasks handed in and waiting for your marking, still to do, and marked.',
        'student' => 'Trainee',
        'feedback' => 'Your feedback to the trainee',
        'marked' => 'Task marked.',
        'empty' => 'No tasks here.',
    ],
];
