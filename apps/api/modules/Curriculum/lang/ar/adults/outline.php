<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'ways' => [
        'copy' => ['label' => 'انسخ برنامجًا', 'hint' => 'من برنامج آخر في الأكاديمية.'],
    ],
    'ai' => [
        'heading' => 'مخطط البرنامج بالذكاء الاصطناعي',
        'fields' => [
            'goal_hint' => 'ماذا يتقن المتدرّب في نهاية البرنامج؟ لا تكتب أسماء أشخاص.',
            'goal_placeholder' => 'مثال: أن يدير المتدرّب مشروعًا صغيرًا بأدوات التخطيط والمتابعة، في ثلاثة أشهر.',
        ],
    ],
    'copy' => [
        'heading' => 'انسخ برنامجًا',
        'done' => 'نُسخ البرنامج: :levels مستوى و:lessons درس.',
        'errors' => [
            'not_empty' => 'يُنسخ البرنامج في برنامج فارغ لم يُنشر بعد.',
        ],
    ],
    'sheet' => [
        'name' => 'مخطط البرنامج',
    ],
    'errors' => [
        'levels' => 'يتسع البرنامج لـ :levels مستوى على الأكثر.',
        'lessons' => 'يتسع البرنامج لـ :lessons درس على الأكثر.',
    ],
];
