<?php

return [
    'errors' => [
        'age_group' => '":value": write adults, or leave it empty.',
        'subjects' => 'Give at least one field.',
        'family_contact' => 'Give the trainee\'s email or number: every trainee signs in to their own account.',
        'student_contact' => 'Give the trainee\'s name and a contact.',
        'no_student' => 'No trainee ":name" with this contact. Import the trainees first, or check the number and name.',
        'no_teacher' => 'No trainer ":value". Import the trainers first.',
        'no_subject' => 'No field called ":value".',
    ],
    'notes' => [
        'new_teacher' => 'A new trainer · fields: :subjects',
        'new_subject' => 'New field: :name',
        'student_exists' => 'The trainee is already here',
        'student_own' => 'A trainee who signs in',
        'schedule_exists' => 'Already enrolled with this trainer for this field',
        'lessons' => '{0} No sessions ahead|{1} One session ahead|[2,*] :count sessions ahead',
        'balance' => '{1} a balance of one session|[2,*] a balance of :count sessions',
        'skipped_dates' => 'The trainee is busy on :dates',
    ],
];
