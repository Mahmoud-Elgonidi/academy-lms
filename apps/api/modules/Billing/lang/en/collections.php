<?php

// The collection timeline, the payment link sent by hand, and the family's balance (docs/architecture/02, section 27, J5a).
return [
    'errors' => [
        'paid' => 'This invoice is paid or cancelled, so there is no payment link to send.',
        'proof_waiting' => 'The family sent a transfer receipt for this invoice; it is waiting for review on the invoice\'s page. Review it instead of sending a new link.',
        'just_sent' => 'The payment link for this invoice was sent a moment ago. It can be sent again in :minutes minutes.',
        'nothing_waiting' => 'No invoice is waiting to be paid for this course now.',
    ],
    'sent' => 'The payment link for invoice :number was sent to the payer.',
    'balance' => [
        'lessons_left' => '{0} No lessons left|{1} One lesson left|[2,*] :count lessons left',
        'expiry' => 'The lessons left expire on :on.',
        'expiry_soon' => ':lessons of the lessons expire on :on. Book them before then so they are not lost.',
        'next_invoice' => 'The next invoice goes out when :mark lessons are left, about :amount, around :on.',
        'next_invoice_now' => 'The balance has reached its renewal mark; the next package\'s invoice goes out soon (about :amount).',
        'waiting' => 'The next package\'s invoice :number is waiting to be paid (:left left).',
        'paused' => 'The course is paused until :until; no lessons are used during the pause.',
        'no_renewal' => 'This course does not renew: it has ended or is ending.',
    ],
];
