<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../shifts.php.
return [
    'roles' => ['teacher' => 'المدرّب'],
    'errors' => [
        'reason_required' => 'اكتب السبب ليعرفه المدرّب.',
        'teacher_drafting' => 'يكتب المدرّب مقترحًا الآن؛ انتظر حتى يرسله.',
    ],
    'comments' => [
        'delayed' => 'تأخّر تاريخ البداية إلى :date حتى تنتهي الجلسات المحجوزة.',
        'withdrawn' => 'سحب المدرّب المقترح.',
    ],
];
