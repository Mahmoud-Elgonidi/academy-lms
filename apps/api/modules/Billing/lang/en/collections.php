<?php

// The collection timeline, the payment link sent by hand, and the family's balance (docs/architecture/02, section 27, J5a).
return [
    'errors' => [
        'paid' => 'This invoice is paid or cancelled, so there is no payment link to send.',
        'proof_waiting' => 'The family sent a transfer receipt for this invoice; it is waiting for review on the invoice\'s page. Review it instead of sending a new link.',
        'just_sent' => '{1} The payment link for this invoice was sent a moment ago. It can be sent again in one minute.|[2,*] The payment link for this invoice was sent a moment ago. It can be sent again in :count minutes.',
        'nothing_waiting' => 'No invoice is waiting to be paid for this course now.',
    ],
    'sent' => 'The payment link for invoice :number was sent to the payer.',
    'balance' => [
        'lessons_left' => '{0} No lessons left|{1} One lesson left|[2,*] :count lessons left',
        'expiry' => 'The lessons left expire on :on.',
        'expiry_soon' => '{1} One of the lessons expires on :on. Book it before then so it is not lost.|[2,*] :count of the lessons expire on :on. Book them before then so they are not lost.',
        'next_invoice' => '{0} The next invoice goes out when no lessons are left, about :amount, around :on.|{1} The next invoice goes out when one lesson is left, about :amount, around :on.|[2,*] The next invoice goes out when :count lessons are left, about :amount, around :on.',
        'next_invoice_now' => 'The balance has reached its renewal mark; the next package\'s invoice goes out soon (about :amount).',
        'waiting' => 'The next package\'s invoice :number is waiting to be paid (:left left).',
        'paused' => 'The course is paused until :until; no lessons are used during the pause.',
        'no_renewal' => 'This course does not renew: it has ended or is ending.',
    ],
    // A number with its counted word for the messages (Translatable::count).
    'count' => [
        'lessons' => '{0} no lessons|{1} one lesson|[2,*] :count lessons',
        'lessons_after' => '{0} nothing|{1} one lesson|[2,*] :count lessons',
        'days_late' => '{1} one day|[2,*] :count days',
    ],
];
