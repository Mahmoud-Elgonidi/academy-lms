<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// No guardians there: the payer is the trainee, so what they receive speaks to them directly.
return [
    'audience' => ['payer' => 'Whoever pays'],
    'invoice_issued' => [
        'body' => 'Your invoice of :total is due by :due. The invoice and ways to pay: :link',
    ],
    'invoice_paid' => [
        'body' => 'Invoice :number is paid in full (:total) and your sessions are confirmed.',
    ],
    'proof_received' => [
        'body' => ':payer sent proof of paying :amount by :method on invoice :number (trainee :student). Check it arrived, then approve it or turn it down.',
    ],
    'renewal_preview' => [
        'title' => 'Your package renews soon',
        'body' => 'A few sessions are left in your package (:subject). When the balance comes down to :lessons, the invoice for the next package ":plan" goes out, about :amount, around :on. Nothing to do yet.',
    ],
    'due_tomorrow' => [
        'body' => 'Your invoice is due tomorrow, :due, with :left left to pay. The invoice and ways to pay: :link',
    ],
    'due_today' => [
        'body' => 'Your invoice is due today (:due), with :left left to pay. The invoice and ways to pay: :link',
    ],
    'overdue' => [
        'body' => 'Your invoice was due on :due, with :left left to pay. Pay it when you can, or message the academy if you need help: :link',
    ],
    'overdue_staff' => [
        'body' => ':payer has not paid invoice :number (trainee :student) two days after it was due; :left is left. They were reminded; follow up if needed.',
    ],
    'lesson_at_risk' => [
        'title' => 'Pay to keep your session',
        'body' => 'Your session on :lesson is unpaid and is cancelled automatically at :until unless invoice :number is paid (:left left). Pay now to keep it: :link',
    ],
    'pay_link' => [
        'body' => 'The academy sent you the payment link for your invoice (:left left). The invoice and ways to pay: :link',
    ],
    'credits_expiring' => [
        'title' => 'Your sessions expire soon',
        'body' => 'You have :lessons in :subject with no time booked yet, expiring on :on. Book your times with the academy before then so nothing is lost.',
    ],
    'renewal_coming' => [
        'title' => 'We will charge your invoice to your card',
        'body' => 'Your next package invoice (:number) of :amount was issued, and is charged automatically to :card at :on. To turn automatic renewal off before then: :off',
    ],
    'charge_succeeded' => [
        'title' => 'Your package renewed automatically',
        'body' => ':amount was charged to :card for invoice :number, and your sessions are confirmed. The invoice: :link',
    ],
    'charge_retry' => [
        'title' => 'Your invoice was not charged',
    ],
    'charge_failed' => [
        'title' => 'Pay your invoice through the link',
        'body' => ':amount could not be charged to :card for invoice :number: :reason Pay it through the link so your sessions stay as booked: :link',
    ],
    'auto_charge_skipped' => [
        'title' => 'Your invoice will not be charged automatically',
    ],
    'card_expiring' => [
        'body' => 'The card :card expires on :on, after which your package no longer renews automatically. Tick "save my card" when you next pay with a new card: :link',
    ],
];
