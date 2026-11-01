<?php

// رسائل وحدة القرآن (Listeners\SendQuranNotifications).
return [
    'audience' => [
        'examiners' => 'من يمتحن القرآن في الأكاديمية',
        'family' => 'الطالب وأولياء أمره',
        'examiner' => 'ممتحن الاختبار',
    ],
    'exam_suggested' => ['title' => 'حان اختبار قرآن: :student', 'body' => 'بلغ :student :stop. احجز الاختبار مع ممتحن.'],
    'exam_booked' => ['title' => 'حُجز اختبار القرآن لـ:student', 'body' => 'اختبار :stop مع :examiner، :when.'],
    'exam_assigned' => ['title' => 'أنت ممتحن :student', 'body' => 'اختبار :stop، :when.'],
    'exam_passed' => ['title' => 'نجح :student في اختبار :stop', 'body' => 'التقدير: :grade. أحسنت!'],
    'exam_failed' => ['title' => 'سيعيد :student اختبار :stop', 'body' => 'التقدير: :grade. نصيحة الممتحن في التطبيق، والإعادة ممكنة من :day.'],
    'certificate_issued' => ['title' => 'شهادة :student جاهزة', 'body' => 'شهادة :stop (:number). افتحها واطبعها: :link'],
];
