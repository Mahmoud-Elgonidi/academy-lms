<?php

return [
    'teachers' => [
        ['name' => 'Sara Mahmoud', 'email' => 'sara.m@example.com', 'phone' => '01012345678', 'gender' => 'female', 'timezone' => 'Egypt', 'subjects' => 'Quran, Tajweed', 'languages' => 'Arabic, English', 'trials' => 'yes', 'max_hours' => '25'],
        ['name' => 'Omar Khaled', 'email' => '', 'phone' => '+966501234567', 'gender' => 'male', 'timezone' => 'Riyadh', 'subjects' => 'Arabic', 'languages' => 'Arabic', 'trials' => 'no', 'max_hours' => ''],
    ],
    'families' => [
        ['student_name' => 'Yousef Ahmed', 'student_email' => '', 'student_phone' => '', 'birth_date' => '2016', 'timezone' => 'London', 'guardian_name' => 'Ahmed Ali', 'guardian_email' => 'ahmed@example.com', 'guardian_phone' => '+447700900123', 'relationship' => 'father', 'language' => 'Arabic'],
        ['student_name' => 'Maryam Ahmed', 'student_email' => '', 'student_phone' => '', 'birth_date' => '2013-05-20', 'timezone' => 'London', 'guardian_name' => 'Ahmed Ali', 'guardian_email' => '', 'guardian_phone' => '+447700900123', 'relationship' => 'father', 'language' => 'Arabic'],
    ],
    'schedules' => [
        ['student_name' => 'Yousef Ahmed', 'contact' => '+447700900123', 'teacher' => 'sara.m@example.com', 'subject' => 'Quran', 'duration' => '30', 'times' => 'Saturday 18:00, Tuesday 18:00', 'clock' => 'teacher', 'start_date' => '', 'remaining' => '5', 'valid_until' => '', 'plan' => '', 'payer' => ''],
        ['student_name' => 'Maryam Ahmed', 'contact' => '+447700900123', 'teacher' => 'Omar Khaled', 'subject' => 'Arabic', 'duration' => '45', 'times' => 'Sunday 5pm', 'clock' => 'student', 'start_date' => '', 'remaining' => '3', 'valid_until' => '', 'plan' => '', 'payer' => ''],
    ],
];
