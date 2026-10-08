<?php

// Plan limits and areas (SharedKernel\Plans), in every module's words.
return [
    'features' => [
        'crm' => 'العملاء المحتملين والحصص التجريبية',
        'online_payments' => 'الدفع الإلكتروني',
        'import' => 'استيراد البيانات',
        'custom_branding' => 'قوالب ألوان خاصة',
        'payroll' => 'مرتبات المدرسين',
    ],
    'limits' => [
        'teachers' => 'المدرسين',
        'active_students' => 'الطلاب النشطين',
        'video_minutes' => 'دقايق الفيديو في الشهر',
        'storage_mb' => 'مساحة الملفات',
    ],
    'reached' => [
        'teachers' => 'باقة الأكاديمية بتسمح بـ :max مدرس بس. كلّم فريق المنصة عشان تكبّرها.',
        'active_students' => 'باقة الأكاديمية بتسمح بـ :max طالب نشط بس. كلّم فريق المنصة عشان تكبّرها.',
        'video_minutes' => 'دقايق الفيديو بتاعة الشهر ده خلصت (:max دقيقة).',
        'storage_mb' => 'مساحة الملفات خلصت (:max ميجا).',
    ],
    'not_in_plan' => ':feature مش في باقة الأكاديمية. كلّم فريق المنصة عشان تضيفها.',
];
