<?php

// Plan limits and areas (SharedKernel\Plans), in every module's words.
return [
    'features' => [
        'crm' => 'العملاء المحتملون والحصص التجريبية',
        'online_payments' => 'الدفع الإلكتروني',
        'import' => 'استيراد البيانات',
        'custom_branding' => 'قوالب ألوان خاصة',
        'payroll' => 'رواتب المعلّمين',
        'ai' => 'المساعد الآلي (الذكاء الاصطناعي)',
    ],
    'limits' => [
        'teachers' => 'المعلّمون',
        'active_students' => 'الطلاب النشطون',
        'video_minutes' => 'دقائق الفيديو في الشهر',
        'storage_mb' => 'مساحة الملفات',
    ],
    'reached' => [
        'teachers' => 'تسمح باقة الأكاديمية بـ :max معلّم فقط. تواصل مع فريق المنصة لترقيتها.',
        'active_students' => 'تسمح باقة الأكاديمية بـ :max طالب نشط فقط. تواصل مع فريق المنصة لترقيتها.',
        'video_minutes' => 'نفدت دقائق الفيديو لهذا الشهر (:max دقيقة).',
        'storage_mb' => 'نفدت مساحة الملفات (:max ميغابايت).',
    ],
    'not_in_plan' => ':feature غير متاحة في باقة الأكاديمية. تواصل مع فريق المنصة لإضافتها.',
];
