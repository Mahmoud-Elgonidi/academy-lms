<?php

return [
    'audience' => ['collections' => 'Who follows up payments (owner, admin and accountant)', 'payer' => 'Whoever pays (guardian or student)', 'accounts' => 'Whoever records payments (owner, admin, accountant)'],
    'invoice_issued' => [
        'title' => 'New invoice :number',
        'body' => 'Invoice for :student of :total, due by :due. The invoice and ways to pay: :link',
    ],
    'invoice_paid' => [
        'title' => 'Payment received, thank you',
        'body' => 'Invoice :number is paid in full (:total) and the lessons are confirmed.',
    ],
    'proof_received' => [
        'title' => 'Proof of payment to review',
        'body' => ':payer sent proof of paying :amount by :method on invoice :number (:student). Check it arrived, then approve it or turn it down.',
    ],
    'proof_approved' => [
        'title' => 'Your payment is confirmed',
        'body' => 'We received :amount on invoice :number. Left to pay: :left.',
    ],
    'proof_rejected' => [
        'title' => 'We could not confirm your payment',
        'body' => 'Your proof of :amount on invoice :number was turned down: :reason. You can send another: :link',
    ],
    'renewal_preview' => [
        'title' => ':student\'s package renews soon',
        'body' => 'A few lessons are left in :student\'s package (:subject). When :lessons lessons are left, the invoice for the next package ":plan" goes out, about :amount, around :on. Nothing to do yet.',
    ],
    'due_tomorrow' => [
        'title' => 'Reminder: invoice :number is due tomorrow',
        'body' => ':student\'s invoice is due tomorrow, :due, with :left left to pay. The invoice and ways to pay: :link',
    ],
    'due_today' => [
        'title' => 'Invoice :number is due today',
        'body' => ':student\'s invoice is due today (:due), with :left left to pay. The invoice and ways to pay: :link',
    ],
    'overdue' => [
        'title' => 'Invoice :number is still unpaid',
        'body' => ':student\'s invoice was due on :due, with :left left to pay. Pay it when you can, or message the academy if you need help: :link',
    ],
    'overdue_staff' => [
        'title' => 'Invoice :days days late',
        'body' => ':payer has not paid invoice :number (:student) two days after it was due; :left is left. They were reminded; follow up if needed.',
    ],
    'lesson_at_risk' => [
        'title' => 'Pay to keep :student\'s lesson',
        'body' => ':student\'s lesson on :lesson is unpaid and is cancelled automatically at :until unless invoice :number is paid (:left left). Pay now to keep it: :link',
    ],
    'pay_link' => [
        'title' => 'Payment link for invoice :number',
        'body' => 'The academy sent you the payment link for :student\'s invoice (:left left). The invoice and ways to pay: :link',
    ],
    'credits_expiring' => [
        'title' => ':student\'s lessons expire soon',
        'body' => ':lessons of :student\'s lessons (:subject) expire on :on. Book them with the academy before then so they are not lost.',
    ],
];
