<?php

return [
    'statuses' => ['assigned' => 'مطلوب', 'submitted' => 'اتسلّم', 'reviewed' => 'اتصحح', 'missed' => 'فات ميعاده'],
    'accepts' => ['any' => 'أي طريقة', 'text' => 'نص', 'audio' => 'تسجيل صوتي', 'file' => 'ملف أو صورة'],
    'attributes' => ['body' => 'الإجابة', 'file' => 'الملف', 'grade' => 'الدرجة', 'feedback' => 'تعليق المدرس'],
    'errors' => [
        'closed' => 'الواجب ده اتصحح خلاص.',
        'not_submitted' => 'الواجب لسه ما اتسلمش.',
        'empty' => 'اكتب إجابة أو ارفع ملف.',
    ],
];
