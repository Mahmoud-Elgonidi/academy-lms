<?php

return [
    // The greeting at the top of the side menu, by the time of day where the viewer is.
    'hello' => [
        'morning' => 'Good morning, :name',
        'day' => 'Good afternoon, :name',
        'evening' => 'Good evening, :name',
        'night' => 'Good evening, :name',
    ],
    // Carbon translatedFormat pattern for today's date under the greeting.
    'today' => 'l, j F',
    // A dialog's button that only closes it (Filament has a cancel and a submit, no close).
    'close' => 'Close',
    // The zone chip in the corner of every panel (shared::chrome.zone-chip): the account's clock, the academy's and the device's.
    'zone' => [
        'open' => 'Your time zone: :zone. Open for details or to change it.',
        'title' => 'Time zone',
        'yours' => 'Your time zone',
        'academy' => 'Academy time zone',
        'device' => 'Your device',
        'device_differs' => 'Your device keeps a different clock from your account.',
        'use_device' => 'Use my device\'s zone',
        'change' => 'Change my zone',
        'search' => 'Search for a city or region',
        'none' => 'No results.',
        'back' => 'Back',
        'current' => 'Current',
        'hint' => 'Times on every screen show on your time zone.',
        'saved' => 'Your time zone is now :zone.',
        'invalid' => 'Choose a time zone from the list.',
        'cannot' => 'The time zone cannot be changed here.',
        // Hiding the chip from its card, and bringing it back (My settings; the console's account menu).
        'hide' => 'Hide this',
        'hidden' => 'The time-zone chip is hidden.',
        'back_in_settings' => 'You can show it again from My settings.',
        'back_in_menu' => 'You can show it again from your account menu.',
        'show' => 'Show the time-zone chip',
        'shown_again' => 'The time-zone chip is back.',
    ],
];
