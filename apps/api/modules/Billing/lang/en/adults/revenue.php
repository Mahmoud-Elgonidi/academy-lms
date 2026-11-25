<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../revenue.php.
return [
    'tiles' => [
        'discounts_hint' => 'Multi-trainee discounts and coupons on the period\'s invoices',
        'forecast_hint' => '{0} No renewals coming|{1} One programme, roughly|[2,*] :n programmes, roughly',
    ],
    'by' => [
        'subjects' => 'Areas',
        'family_hint' => 'A combined invoice\'s money is shared between its programmes in proportion to each.',
    ],
    'kinds' => [
        'new' => 'New programme',
        'extra' => 'Extra sessions',
    ],
    'forecast' => [
        'hint' => 'From each programme\'s balance and sessions a week, priced with its discount and VAT. Cancelled or moved sessions shift the date.',
        'more' => '{1} and one more programme|[2,*] and :n more programmes',
    ],
    'export' => [
        'students' => 'Trainees',
    ],
];
