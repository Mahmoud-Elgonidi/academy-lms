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
        'body' => 'A few lessons are left in :student\'s package (:subject). When the balance comes down to :lessons, the invoice for the next package ":plan" goes out, about :amount, around :on. Nothing to do yet.',
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
        'title' => 'Invoice :days late',
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
        'body' => ':student has :lessons in :subject with no time booked yet, expiring on :on. Book their times with the academy before then so nothing is lost.',
    ],
    'renewal_coming' => [
        'title' => 'We will charge :student\'s invoice to your card',
        'body' => ':student\'s next package invoice (:number) of :amount was issued, and is charged automatically to :card at :on. To turn automatic renewal off before then: :off',
    ],
    'charge_succeeded' => [
        'title' => ':student\'s package renewed automatically',
        'body' => ':amount was charged to :card for invoice :number, and the lessons are confirmed. The invoice: :link',
    ],
    'charge_retry' => [
        'title' => ':student\'s invoice was not charged',
        'body' => ':amount could not be charged to :card for invoice :number: :reason We will try once more at :retry_at, or you can pay now: :link',
    ],
    'charge_failed' => [
        'title' => 'Pay :student\'s invoice through the link',
        'body' => ':amount could not be charged to :card for invoice :number: :reason Pay it through the link so the lessons stay as booked: :link',
    ],
    'auto_charge_skipped' => [
        'title' => ':student\'s invoice will not be charged automatically',
        'body' => 'We will not charge invoice :number (:amount) automatically because :reason. Pay it through the link: :link',
    ],
    'card_expiring' => [
        'title' => 'Your saved card expires soon',
        'body' => 'The card :card expires on :on, after which packages no longer renew automatically. Tick "save my card" when you next pay with a new card: :link',
    ],
    'card_stopped' => [
        'title' => 'Automatic renewal with your card has stopped',
        'body' => 'The academy turned off the payment account your card :card was saved on, so it can no longer renew automatically. Invoices come with their payment link as usual: :link',
    ],
];
