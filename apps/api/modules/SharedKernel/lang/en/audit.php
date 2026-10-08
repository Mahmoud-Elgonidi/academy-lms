<?php

return [
    // By the action's two parts: 'billing.gateway_saved' is actions.billing.gateway_saved.
    'actions' => [
        'billing' => [
            'gateway_saved' => 'Payment account ":label" saved (:gateway · :mode)',
            'payment_recorded' => 'Payment of :amount (:method) recorded on invoice :number',
            'invoice_voided' => 'Invoice :number voided: :reason',
            'settings_saved' => 'Tax and invoice settings changed',
            'stripe_connected' => 'Stripe account :account connected (:mode)',
            'stripe_disconnected' => 'Stripe account :account disconnected',
            'proof_rejected' => 'Proof of :amount on invoice :number turned down: :reason',
        ],
        'policy' => [
            'saved' => 'Cancellation policy version :version',
        ],
        'session' => [
            'cancel_waived' => 'Lesson cancelled with a policy waiver',
            'review_resolved' => 'Lesson under review decided: :outcome',
        ],
        'shift' => [
            'approved_override' => 'Shifts approved without the teacher: :reason',
        ],
        'enrollment' => [
            'teacher_changed' => 'A student\'s teacher changed from :starting',
        ],
        'payroll' => [
            'month_closed' => 'Payroll for :month closed',
            'adjusted' => ':kind of :amount: :note',
            'adjustment_reversed' => ':kind of :amount taken back',
            'rates_saved' => 'Rate card ":name" saved',
        ],
        'import' => [
            'done' => ':file imported',
        ],
        'identity' => [
            'password_set' => ':name chose a password from their welcome link',
            'password_changed' => 'Password changed',
            'two_factor_enabled' => ':name turned on two-step sign-in',
            'two_factor_disabled' => ':name turned off two-step sign-in',
            'recovery_code_used' => ':name signed in with a recovery code',
            'recovery_codes_renewed' => ':name made new recovery codes',
            'two_factor_reset' => 'Two-step sign-in of :name reset by the platform team: :reason',
        ],
        'people' => [
            'consent_granted' => ':guardian agreed to :student\'s account (:via)',
            'consent_recorded' => 'A guardian\'s consent for :student recorded by the office: :note',
            'consent_withdrawn' => ':guardian withdrew their consent to :student\'s account',
            'consent_age_saved' => 'Guardians now consent for students under :age',
        ],
        'impersonation' => [
            'started' => 'Signed in as :user for :minutes minutes: :reason',
            'ended' => 'Sign-in as :user ended: :how',
        ],
        'academy' => [
            'created' => 'Academy created, owner :owner',
            'suspended' => 'Academy suspended: :reason',
            'reactivated' => 'Academy back on',
        ],
        'platform' => [
            'signed_in' => 'Signed in to the console',
        ],
    ],
    'values' => [
        'method' => ['bank_transfer' => 'bank transfer', 'instapay' => 'InstaPay', 'wallet' => 'mobile wallet', 'cash' => 'cash', 'card_terminal' => 'card terminal', 'other' => 'other', 'online' => 'online'],
        'kind' => ['bonus' => 'Bonus', 'deduction' => 'Deduction'],
        'outcome' => ['completed' => 'taught', 'teacher_absent' => 'teacher absent', 'student_absent' => 'student absent', 'cancelled_makeup' => 'cancelled with a makeup'],
        'mode' => ['test' => 'test', 'live' => 'live'],
        'via' => ['family' => 'from their account', 'link' => 'from the link they got', 'staff' => 'recorded by the office'],
        'how' => ['exit' => 'they left', 'expired' => 'time ran out', 'ended' => 'ended from the console'],
    ],
    'groups' => [
        'billing' => 'Money',
        'scheduling' => 'Lessons and policy',
        'payroll' => 'Payroll',
        'import' => 'Imports',
        'identity' => 'Accounts',
        'people' => 'Students and guardians',
        'impersonation' => 'Platform sign-ins',
        'platform' => 'Platform',
    ],
    'actor' => [
        'system' => 'System',
        'platform' => 'Platform team · :name',
        'platform_as' => 'Platform team · :name, as :user',
    ],
];
