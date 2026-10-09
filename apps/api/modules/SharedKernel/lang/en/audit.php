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
            'coupon_saved' => 'Saved coupon :code (:value)',
            'coupon_applied' => 'Put coupon :code (:value) on a subscription',
            'coupon_removed' => 'Took coupon :code off a subscription',
            'sibling_discount_saved' => 'Sibling discount: second child :second%, third and after :more%',
            'extra_lessons' => 'Invoice :number for :lessons extra lessons (:total)',
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
            'contact_changed' => "Changed :name's email or number (ways to sign in)",
            'account_disabled' => "Turned off :name's account",
            'account_enabled' => "Turned :name's account back on",
            'password_set' => ':name chose a password from their welcome link',
            'password_changed' => 'Password changed',
            'two_factor_enabled' => ':name turned on two-step sign-in',
            'two_factor_disabled' => ':name turned off two-step sign-in',
            'recovery_code_used' => ':name signed in with a recovery code',
            'recovery_codes_renewed' => ':name made new recovery codes',
            'two_factor_reset' => 'Two-step sign-in of :name reset by the platform team: :reason',
            'social_linked' => ':name linked a :provider account (:via)',
            'social_unlinked' => ':name unlinked a :provider account',
        ],
        'people' => [
            'consent_granted' => ':guardian agreed to :student\'s account (:via)',
            'consent_recorded' => 'A guardian\'s consent for :student recorded by the office: :note',
            'consent_withdrawn' => ':guardian withdrew their consent to :student\'s account',
            'consent_age_saved' => 'Guardians now consent for students under :age',
        ],
        'classroom' => [
            'recording_settings_saved' => 'Lesson recording settings: :recording, :media, :days days',
            'recording_consent_given' => ':by agreed to :person\'s lessons being recorded',
            'recording_consent_recorded' => 'Office recorded :person\'s consent to recordings: :note',
            'recording_consent_withdrawn' => ':by withdrew consent to recording :person\'s lessons',
            'recording_held' => 'Held the recording of :lesson: :reason',
            'recording_released' => 'Released the recording of :lesson',
            'recording_deleted' => 'Removed the recording of :lesson: :reason',
        ],
        'support' => [
            'complaint_validated' => 'Complaint :ticket about :teacher confirmed: :note',
            'complaint_dismissed' => 'Complaint :ticket dismissed: :note',
            'targets_saved' => 'Support answer targets changed',
        ],
        'impersonation' => [
            'started' => 'Signed in as :user for :minutes minutes: :reason',
            'ended' => 'Sign-in as :user ended: :how',
        ],
        'academy' => [
            'created' => 'Academy created, owner :owner',
            'suspended' => 'Academy suspended: :reason',
            'reactivated' => 'Academy back on',
            'plan_changed' => 'The academy moved to the :plan plan: :reason',
        ],
        'platform' => [
            'signed_in' => 'Signed in to the console',
            'plan_saved' => 'The :plan plan edited',
        ],
    ],
    'values' => [
        'method' => ['bank_transfer' => 'bank transfer', 'instapay' => 'InstaPay', 'wallet' => 'mobile wallet', 'cash' => 'cash', 'card_terminal' => 'card terminal', 'other' => 'other', 'online' => 'online'],
        'kind' => ['bonus' => 'Bonus', 'deduction' => 'Deduction'],
        'outcome' => ['completed' => 'taught', 'teacher_absent' => 'teacher absent', 'student_absent' => 'student absent', 'cancelled_makeup' => 'cancelled with a makeup'],
        'mode' => ['test' => 'test', 'live' => 'live'],
        'via' => ['self' => 'themself', 'guardian' => 'as guardian', 'family' => 'from their account', 'link' => 'from the link they got', 'staff' => 'recorded by the office', 'email' => 'first sign-in with the same email', 'settings' => 'from their settings'],
        'provider' => ['google' => 'Google', 'apple' => 'Apple'],
        'how' => ['exit' => 'they left', 'expired' => 'time ran out', 'ended' => 'ended from the console'],
        'recording' => ['off' => 'no recording', 'manual' => 'the teacher starts it', 'auto' => 'every lesson by itself'],
        'media' => ['by_age' => 'by age', 'video' => 'video', 'audio' => 'sound only'],
    ],
    'groups' => [
        'billing' => 'Money',
        'scheduling' => 'Lessons and policy',
        'payroll' => 'Payroll',
        'import' => 'Imports',
        'identity' => 'Accounts',
        'people' => 'Students and guardians',
        'classroom' => 'Lesson recordings',
        'support' => 'Support and complaints',
        'impersonation' => 'Platform sign-ins',
        'platform' => 'Platform',
    ],
    'actor' => [
        'system' => 'System',
        'platform' => 'Platform team · :name',
        'platform_as' => 'Platform team · :name, as :user',
    ],
];
