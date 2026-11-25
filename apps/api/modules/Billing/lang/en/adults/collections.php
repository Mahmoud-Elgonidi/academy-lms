<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../collections.php.
return [
    'errors' => [
        'proof_waiting' => 'The payer sent a transfer receipt for this invoice; it is waiting for review on the invoice\'s page. Review it instead of sending a new link.',
        'nothing_waiting' => 'No invoice is waiting to be paid for this programme now.',
    ],
    'balance' => [
        'lessons_left' => '{0} No sessions left|{1} One session left|[2,*] :count sessions left',
        'expiry' => 'The sessions left expire on :on.',
        'expiry_soon' => '{1} One of the sessions expires on :on. Book it before then so it is not lost.|[2,*] :count of the sessions expire on :on. Book them before then so they are not lost.',
        'next_invoice' => '{0} The next invoice goes out when no sessions are left, about :amount, around :on.|{1} The next invoice goes out when one session is left, about :amount, around :on.|[2,*] The next invoice goes out when :count sessions are left, about :amount, around :on.',
        'paused' => 'The programme is paused until :until; no sessions are used during the pause.',
        'no_renewal' => 'This programme does not renew: it has ended or is ending.',
    ],
    'count' => [
        'lessons' => '{0} no sessions|{1} one session|[2,*] :count sessions',
        'lessons_after' => '{0} nothing|{1} one session|[2,*] :count sessions',
    ],
];
