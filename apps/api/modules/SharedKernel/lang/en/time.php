<?php

return [
    // Carbon translatedFormat pattern for a date and time in the viewer's zone.
    'label' => 'D j M · H:i',
    // Carbon translatedFormat pattern for a day alone (a day on the academy's calendar).
    'day' => 'D j M',
    // ISO weekdays: 1 Monday … 7 Sunday.
    'days' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
    // The regions of the zone pickers (Timezones::REGIONS).
    'regions' => [
        'Asia' => 'Asia',
        'Africa' => 'Africa',
        'Europe' => 'Europe',
        'America' => 'The Americas',
        'Australia' => 'Australia',
        'Indian' => 'Indian Ocean',
        'Atlantic' => 'Atlantic Ocean',
        'Pacific' => 'Pacific Ocean',
        'Antarctica' => 'Antarctica',
        'Arctic' => 'Arctic',
        'UTC' => 'Universal time',
    ],
    // Under a page's title: which clock its times are on (ZoneHint).
    'hint' => [
        'yours' => 'Times here are on your time zone: :zone.',
        'teacher' => 'Shift hours here are on each teacher\'s own time zone, shown beside their name. Yours: :zone.',
        'lessons_teacher' => 'Lesson times here are on your time zone: :zone, with the teacher\'s beside them.',
        'academy' => 'Times here are on the academy\'s time zone: :zone.',
    ],
];
