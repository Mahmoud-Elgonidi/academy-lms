<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../invoices.php.
return [
    'family' => 'Combined invoice (:n programmes)',
    'fields' => [
        'student' => 'Trainee',
    ],
    'doc' => [
        'open_account' => 'Trainee account →',
        'extra_terms' => ':unit a session · valid :days days from payment',
        'on_payment' => 'Once this is paid, the block\'s minutes go on the trainee\'s balance and the waiting sessions are confirmed in order.',
    ],
    'empty' => [
        'hint' => 'Invoices go out by themselves when you enroll a trainee, and when their credit runs low.',
    ],
];
