<?php

// إعدادات القرآن في الأكاديمية (SaveQuranSettings).
return [
    'riwayat' => ['hafs' => 'حفص عن عاصم'],
    'mushafs' => ['madinah_604' => 'مصحف المدينة (604 صفحات)'],
    'examiners' => ['other_teacher' => 'معلّم آخر', 'supervisor' => 'مشرف', 'any_teacher' => 'أي معلّم، ومنهم معلّم الطالب'],
    'attributes' => [
        'hifz_daily_pages' => 'صفحات الورد اليومي', 'riwaya' => 'الرواية', 'mushaf' => 'المصحف', 'default_unit' => 'الوحدة الافتراضية',
        'default_new_amount' => 'المقدار الجديد الافتراضي', 'default_near_portions' => 'المراجعة القريبة الافتراضية', 'default_far_pages' => 'المراجعة البعيدة الافتراضية',
        'mistake_types' => 'أنواع الأخطاء', 'grade_scale' => 'سلّم التقديرات', 'memorization_threshold' => 'حد الحفظ',
        'auto_recitation_check' => 'التسميع الآلي', 'exams_enabled' => 'الاختبارات', 'examiner' => 'المختبِر',
        'certificates_enabled' => 'الشهادات', 'certificate_title' => 'عنوان الشهادة', 'certificate_signature' => 'توقيع الشهادة',
        'certificates_family' => 'عرض الشهادات على الأسرة',
    ],
    'errors' => [
        'types' => 'اثنا عشر نوعًا من الأخطاء على الأكثر.',
        'type_key' => 'لكل نوع من الأخطاء مفتاح خاص به: حروف لاتينية صغيرة وأرقام و_.',
        'type_weight' => 'وزن الخطأ من 0 إلى 5.',
        'type_name' => 'اكتب لكل نوع جديد اسمًا بالعربية واسمًا بالإنجليزية، لا يزيد كل منهما على 40 حرفًا.',
        'type_built_in' => 'يبقى الخطأ والتردد والتجويد دائمًا (ويمكن تغيير أوزانها).',
        'scale' => 'ينتهي كل تقدير فوق الذي قبله، و50 على الأكثر.',
    ],
];
