<?php

// A guardian's consent to a child's account. If the meaning below changes, raise GuardianConsent::VERSION.
return [
    'refusal' => 'Your account is waiting for your parent\'s consent. We sent them a message; once they agree, you can sign in.',

    'text' => [
        'title' => 'Your consent to :student\'s account',
        'intro' => ':academy gives :student lessons online. For :student to sign in, join lessons and hand in homework on their own, we need your consent as their guardian.',
        'sections' => [
            'keep' => [
                'title' => 'What we keep',
                'body' => 'Name and date of birth, lesson times and attendance (who joined and when), the teacher\'s reports, the homework :student hands in (voice recordings included), and lesson ratings.',
            ],
            'lessons' => [
                'title' => 'Live lessons',
                'body' => 'Sound and video pass live between :student and the teacher during the lesson only. Lessons are not recorded.',
            ],
            'who' => [
                'title' => 'Who sees it',
                'body' => 'You, the teacher, and the academy\'s staff. Teachers do not see your phone number or email.',
            ],
            'how_long' => [
                'title' => 'For how long',
                'body' => 'While :student is enrolled, and afterwards only what the law requires.',
            ],
            'rights' => [
                'title' => 'Your rights',
                'body' => 'You can see :student\'s data, or ask the academy to correct or delete it. You can withdraw your consent at any time from your account ("Children\'s privacy"), and :student is signed out at once.',
            ],
        ],
        'agree' => 'I am :student\'s guardian, and I agree to them using their account and lessons this way.',
    ],

    'state' => [
        'waiting' => 'Waiting for your consent',
        'granted' => 'You agreed',
        'granted_by' => ':name agreed on :when',
        'you_agreed' => 'You agreed on :when',
        'office_recorded' => 'The office recorded a guardian\'s consent on :when',
        'not_needed' => 'No consent needed',
        'withdrawn' => 'Consent withdrawn',
    ],
    'via' => ['family' => 'from their account', 'link' => 'from the link they got', 'staff' => 'recorded by the office'],

    'actions' => [
        'agree' => 'I agree',
        'withdraw' => 'Withdraw consent',
        'withdraw_confirm' => ':student will be signed out on every device, and cannot sign in or join with their link until you agree again. Are you sure?',
    ],

    'family' => [
        'nav' => 'Children\'s privacy',
        'title' => 'Children\'s privacy',
        'lead' => 'Children under :age need your consent to sign in and join on their own.',
        'banner' => '{1} :names is waiting for your consent to sign in.|[2,*] :names are waiting for your consent to sign in.',
        'banner_action' => 'Read and agree',
        'agreed' => 'Done: :student can sign in now.',
        'withdrawn' => 'You withdrew your consent, and :student was signed out.',
        'none' => 'No child needs your consent.',
    ],

    'link' => [
        'tick' => 'Tick "I am the guardian" first.',
        'title' => 'Guardian consent',
        'saved' => 'Thank you! :student can sign in now.',
        'already' => 'Consent was given :when. To withdraw it, use your account ("Children\'s privacy").',
        'not_needed' => ':student does not need a guardian\'s consent.',
        'expired' => 'This link has ended or is not yours. Ask the academy for a new one, or agree from your account.',
    ],

    'office' => [
        'nav' => 'Guardian consents',
        'title' => 'Guardian consents',
        'lead' => 'Students under :age cannot sign in or join with their own link until a guardian agrees. Guardians are asked on their own as soon as they are linked to the student.',
        'tabs' => ['waiting' => 'Waiting', 'granted' => 'Agreed', 'withdrawn' => 'Withdrawn'],
        'columns' => ['student' => 'Student', 'age' => 'Age', 'guardians' => 'Guardians', 'state' => 'State', 'since' => 'Since'],
        'age' => ':age',
        'age_unknown' => 'Unknown',
        'no_guardian' => 'No guardian linked',
        'resend' => 'Ask again',
        'resent' => '{0} There is no guardian to ask.|{1} One guardian was asked.|[2,*] :count guardians were asked.',
        'record' => 'Record a consent we hold',
        'record_hint' => 'A guardian\'s consent you hold outside the system: a signed form, a call on a date. Say exactly what you have.',
        'recorded' => 'Consent recorded; the student can sign in.',
        'withdraw' => 'Withdraw consent (at the guardian\'s request)',
        'withdraw_hint' => 'When the guardian asks you to. The student is signed out on every device.',
        'withdrawn' => 'Consent withdrawn; the student was signed out.',
        'history' => 'History',
        'history_row' => ':state · :who · :when',
        'settings' => 'Consent age',
        'settings_hint' => 'Guardians consent for students younger than this. 18 is the safest (Saudi Arabia, Egypt); 16 or 13 only where the law you work under allows.',
        'settings_saved' => 'Saved.',
        'under' => 'Under :age',
    ],

    'attributes' => ['note' => 'what you hold', 'guardian' => 'guardian'],

    'errors' => [
        'changed' => 'The wording changed. Read it again and agree.',
        'not_needed' => 'This student does not need a guardian\'s consent.',
        'already' => 'This student already has a guardian\'s consent.',
        'none' => 'There is no consent to withdraw.',
        'try_again' => 'Something went wrong. Try again.',
        'note' => 'Write the reason in at least :min characters.',
        'not_their_guardian' => 'This guardian is not linked to the student.',
    ],
];
