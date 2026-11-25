<?php

// The template's examples in an academy that trains adults only.
return [
    'teachers' => [
        ['name' => 'Eng. Sara Mahmoud', 'subjects' => 'Project management, Data analysis', 'ages' => ''],
        ['name' => 'Eng. Omar Khaled', 'subjects' => 'Project management'],
    ],
    'families' => [
        ['student_name' => 'Yousef Ahmed', 'student_email' => 'yousef@example.com', 'student_phone' => '+447700900123', 'gender' => 'male', 'birth_date' => '1990'],
        ['student_name' => 'Maryam Ali', 'student_email' => 'maryam@example.com', 'student_phone' => '', 'gender' => 'female', 'birth_date' => '1987-05-20'],
    ],
    'schedules' => [
        ['subject' => 'Project management', 'clock' => 'trainer'],
        ['student_name' => 'Maryam Ali', 'contact' => 'maryam@example.com', 'teacher' => 'Eng. Omar Khaled', 'subject' => 'Project management', 'clock' => 'trainee'],
    ],
];
