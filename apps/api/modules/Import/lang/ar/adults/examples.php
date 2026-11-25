<?php

// أمثلة النموذج في أكاديمية تدرّب الكبار فقط.
return [
    'teachers' => [
        ['name' => 'م. سارة محمود', 'subjects' => 'إدارة المشاريع، تحليل البيانات', 'ages' => ''],
        ['name' => 'م. عمر خالد', 'subjects' => 'إدارة المشاريع'],
    ],
    'families' => [
        ['student_name' => 'يوسف أحمد', 'student_email' => 'yousef@example.com', 'student_phone' => '+447700900123', 'gender' => 'ذكر', 'birth_date' => '1990'],
        ['student_name' => 'مريم علي', 'student_email' => 'maryam@example.com', 'student_phone' => '', 'gender' => 'أنثى', 'birth_date' => '1987-05-20'],
    ],
    'schedules' => [
        ['subject' => 'إدارة المشاريع', 'clock' => 'المدرّب'],
        ['student_name' => 'مريم علي', 'contact' => 'maryam@example.com', 'teacher' => 'م. عمر خالد', 'subject' => 'إدارة المشاريع', 'clock' => 'المتدرّب'],
    ],
];
