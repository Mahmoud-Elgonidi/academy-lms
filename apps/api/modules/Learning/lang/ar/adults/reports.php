<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../reports.php.
return [
    'kinds' => ['lesson' => 'تقرير جلسة'],
    'attributes' => [
        'notes_for_student' => 'ملاحظات للمتدرّب',
        'items' => 'ما غُطّي في الجلسة',
    ],
    'oversight' => [
        'subtitle' => 'كل جلسة انتهت تحتاج إلى تقرير من المدرّب خلال :hours ساعة. هنا المتأخر، وما ينتظر المراجعة، وما رُوجع.',
        'teacher' => 'المدرّب',
        'student' => 'المتدرّب',
        'lesson' => 'الجلسة',
        'review_note' => 'ملاحظة للمدرّب (اختياري)',
    ],
    'detail' => [
        'no_items' => 'هذا التقرير من دون بنود برنامج (المتدرّب لا يتبع برنامجًا).',
        'for_family' => 'للمتدرّب',
        'homework' => 'المهمة',
    ],
];
