<?php

return [
    'teachers' => [
        ['name' => 'أ. سارة محمود', 'email' => 'sara.m@example.com', 'phone' => '01012345678', 'gender' => 'أنثى', 'timezone' => 'مصر', 'subjects' => 'قرآن، تجويد', 'languages' => 'عربي، إنجليزي', 'trials' => 'نعم', 'max_hours' => '25'],
        ['name' => 'أ. عمر خالد', 'email' => '', 'phone' => '+966501234567', 'gender' => 'ذكر', 'timezone' => 'الرياض', 'subjects' => 'لغة عربية', 'languages' => 'عربي', 'trials' => 'لا', 'max_hours' => ''],
    ],
    'families' => [
        ['student_name' => 'يوسف أحمد', 'student_email' => '', 'student_phone' => '', 'birth_date' => '2016', 'timezone' => 'لندن', 'guardian_name' => 'أحمد علي', 'guardian_email' => 'ahmed@example.com', 'guardian_phone' => '+447700900123', 'relationship' => 'أب', 'language' => 'عربي'],
        ['student_name' => 'مريم أحمد', 'student_email' => '', 'student_phone' => '', 'birth_date' => '2013-05-20', 'timezone' => 'لندن', 'guardian_name' => 'أحمد علي', 'guardian_email' => '', 'guardian_phone' => '+447700900123', 'relationship' => 'أب', 'language' => 'عربي'],
    ],
    'schedules' => [
        ['student_name' => 'يوسف أحمد', 'contact' => '+447700900123', 'teacher' => 'sara.m@example.com', 'subject' => 'قرآن', 'duration' => '30', 'times' => 'السبت 18:00، الثلاثاء 18:00', 'clock' => 'المدرس', 'start_date' => '', 'remaining' => '5', 'valid_until' => '', 'plan' => '', 'payer' => ''],
        ['student_name' => 'مريم أحمد', 'contact' => '+447700900123', 'teacher' => 'أ. عمر خالد', 'subject' => 'لغة عربية', 'duration' => '45', 'times' => 'الأحد 5 م', 'clock' => 'الطالب', 'start_date' => '', 'remaining' => '3', 'valid_until' => '', 'plan' => '', 'payer' => ''],
    ],
];
