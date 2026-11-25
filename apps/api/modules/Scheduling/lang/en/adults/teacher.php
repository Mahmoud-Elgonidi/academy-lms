<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../teacher.php.
return [
    'week' => [
        'count' => '{0} No sessions|{1} One session|[2,*] :n sessions',
        'none' => 'No sessions on these days.',
    ],
    'availability' => [
        'subtitle' => 'Times are on your clock (:zone). Trainees are booked inside these hours only.',
        'propose_hint' => 'Set the hours you can train each week and send them to the academy for approval.',
        'from_hint' => 'Taking hours away needs :days days\' notice, for the trainees booked in them.',
    ],
];
