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
];
