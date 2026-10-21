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
];
