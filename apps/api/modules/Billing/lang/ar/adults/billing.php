<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../billing.php.
return [
    'lines' => [
        'extra' => ':subject — جلسات إضافية (:minutes دقيقة للجلسة)',
        'sibling' => 'خصم تعدد المتدرّبين (:rate)',
        'late_join' => 'جلسات فاتت قبل الانضمام إلى المجموعة (:count)',
    ],
    'void_course_ended' => 'انتهى البرنامج التدريبي',
];
