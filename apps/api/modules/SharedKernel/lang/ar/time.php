<?php

return [
    // Carbon translatedFormat pattern for a date and time in the viewer's zone.
    'label' => 'l j F · H:i',
    // Carbon translatedFormat pattern for a day alone (a day on the academy's calendar).
    'day' => 'l j F',
    // ISO weekdays: 1 Monday … 7 Sunday.
    'days' => [1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت', 7 => 'الأحد'],
    // The regions of the zone pickers (Timezones::REGIONS).
    'regions' => [
        'Asia' => 'آسيا',
        'Africa' => 'أفريقيا',
        'Europe' => 'أوروبا',
        'America' => 'الأمريكتان',
        'Australia' => 'أستراليا',
        'Indian' => 'المحيط الهندي',
        'Atlantic' => 'المحيط الأطلسي',
        'Pacific' => 'المحيط الهادئ',
        'Antarctica' => 'القارة القطبية الجنوبية',
        'Arctic' => 'القطب الشمالي',
        'UTC' => 'التوقيت العالمي',
    ],
    // Under a page's title: which clock its times are on (ZoneHint).
    'hint' => [
        'yours' => 'المواعيد هنا بتوقيتك: :zone.',
        'teacher' => 'ساعات الدوام هنا بتوقيت كل معلّم، ويظهر توقيته بجانب اسمه. توقيتك: :zone.',
        'lessons_teacher' => 'مواعيد الحصص هنا بتوقيتك: :zone، وبجانبها توقيت المعلّم.',
        'academy' => 'المواعيد هنا بتوقيت الأكاديمية: :zone.',
    ],
];
