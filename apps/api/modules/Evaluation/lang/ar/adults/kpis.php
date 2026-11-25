<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'nav' => 'أداء المدرّبين',
    'title' => 'أداء المدرّبين',
    'subtitle' => 'آخر 90 يومًا. أمام كل مؤشر هدفه، والأخضر ضمن الهدف والأحمر يحتاج إلى متابعة. الجلسات التي سببها المنصة أو مشكلة عامة يمكنك استبعادها من أرقام المدرّب من صفحته.',
    'mine' => ['comments' => 'كلام المتدرّبين'],
    'teacher' => 'المدرّب',
    'names' => [
        'taught' => 'وقت التدريب',
        'rating' => 'تقييم المتدرّبين',
        'complaints' => 'الشكاوى المؤكدة لكل 100 جلسة',
        'homework' => 'تصحيح المهام خلال 48 ساعة',
    ],
    'sample' => [
        'punctuality' => '{1} من جلسة واحدة|[2,*] من :count جلسة',
        'absence' => '{1} من جلسة واحدة|[2,*] من :count جلسة',
        'late_cancel' => '{1} من جلسة واحدة|[2,*] من :count جلسة',
        'taught' => '{1} من جلسة واحدة|[2,*] من :count جلسة',
        'early_leave' => '{1} من جلسة واحدة|[2,*] من :count جلسة',
        'complaints' => '{1} من جلسة واحدة|[2,*] من :count جلسة',
        'homework' => '{1} من مهمة واحدة|[2,*] من :count مهمة',
    ],
    'events' => [
        'absent' => 'لم يحضر الجلسة',
        'late_cancel' => 'ألغى الجلسة قبل موعدها بوقت قصير',
    ],
    'exclude_heading' => 'هل تستبعد هذه الجلسة من أرقام المدرّب؟',
    'exclude_hint' => 'لن تُحسب الجلسة في أي مؤشر للمدرّب، ويُسجَّل السبب.',
    'errors' => ['lesson' => 'هذه الجلسة غير موجودة.'],
];
