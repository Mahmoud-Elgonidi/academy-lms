<?php

return [
    // The greeting at the top of the side menu, by the time of day where the viewer is.
    'hello' => [
        'morning' => 'صباح الخير، :name',
        'day' => 'نهارك سعيد، :name',
        'evening' => 'مساء الخير، :name',
        'night' => 'مساء الخير، :name',
    ],
    // Carbon translatedFormat pattern for today's date under the greeting.
    'today' => 'l j F',
    // A dialog's button that only closes it (Filament has a cancel and a submit, no close).
    'close' => 'إغلاق',
    // The zone chip in the corner of every panel (shared::chrome.zone-chip): the account's clock, the academy's and the device's.
    'zone' => [
        'open' => 'توقيتك: :zone. افتح لترى التفاصيل أو تغيّر التوقيت.',
        'title' => 'التوقيت',
        'yours' => 'توقيتك',
        'academy' => 'توقيت الأكاديمية',
        'device' => 'توقيت جهازك',
        'device_differs' => 'ساعة جهازك تختلف عن توقيت حسابك.',
        'use_device' => 'استخدم توقيت جهازي',
        'change' => 'تغيير توقيتي',
        'search' => 'ابحث عن مدينة أو منطقة',
        'none' => 'لا توجد نتائج.',
        'back' => 'رجوع',
        'current' => 'الحالي',
        'hint' => 'تظهر المواعيد في كل الشاشات بتوقيتك.',
        'saved' => 'حُفظ توقيتك: :zone.',
        'invalid' => 'اختر توقيتًا من القائمة.',
        'cannot' => 'لا يمكن تغيير التوقيت من هنا.',
        // Hiding the chip from its card, and bringing it back (My settings; the console's account menu).
        'hide' => 'أخفِ المؤشّر',
        'hidden' => 'أخفيت مؤشّر التوقيت.',
        'back_in_settings' => 'يمكنك إظهاره من إعداداتي.',
        'back_in_menu' => 'يمكنك إظهاره من قائمة حسابك.',
        'show' => 'أظهر مؤشّر التوقيت',
        'shown_again' => 'ظهر مؤشّر التوقيت.',
    ],
];
