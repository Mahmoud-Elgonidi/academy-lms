<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../leads.php.
// The refusals under errors are errors_adults' words: the whole academy speaks to the one asking for themself.
return [
    'subtitle' => 'Everyone who asked about sessions, from first contact to a subscription. Book them an introductory session from "Find a trainer", and the trainer assesses them after it.',
    'stages' => [
        'trial_booked' => 'Introductory session booked',
    ],
    'funnel' => [
        'booked' => 'Introductory sessions booked',
        'attended' => 'Attended an introductory session',
        'rate' => 'Subscribed after an introductory session',
    ],
    'card' => [
        'trial_at' => 'introductory session :when',
        'waiting' => 'waiting for the trainer\'s assessment',
    ],
    'sections' => [
        'learner' => 'Trainee',
    ],
    'fields' => [
        'for_child' => 'The sessions are for someone else',
        'learner_name' => 'Trainee\'s name',
        'subject_id' => 'Area',
        'teacher_gender' => 'Preferred trainer',
    ],
    'hints' => [
        'contact_name' => 'The trainee, or whoever gets in touch for them',
    ],
    'teacher_genders' => ['female' => 'A woman trainer', 'male' => 'A man trainer'],
    'settings' => [
        'after_trial' => 'After the introductory session',
        'after_trial_hint' => 'The trainer\'s report reaches the trainee as soon as it is written, with their own link on WhatsApp.',
        'trial_offer' => 'Send an offer with the introductory session\'s report',
        'trial_offer_hint' => 'Off the first plan for each trainee who attended their free introductory session and has not started the area, once per trainee and area. It comes with the report, and the trainee is reminded once in its last day. It does not add to the multi-trainee discount: the bigger of the two applies.',
        'trial_offer_hours_hint' => 'From 24 to 336 hours (two weeks). Offers already sent stay as the trainee was promised.',
        'second_trial' => 'A free second introductory session with another trainer',
        'second_trial_hint' => 'Shown on the session\'s report for trainees the trainer did not suit: once per trainee and area, within 14 days of the first session, while they have not started the area.',
    ],
    'page' => [
        'no_subject' => 'Area not known yet',
        'trials' => 'Introductory sessions',
        'no_trials' => 'No introductory session yet.',
        'teacher_time' => ':time for the trainer',
        'assessment' => 'Trainer\'s assessment',
        'per_week' => '{1} one session a week|[2,*] :count sessions a week',
        'waiting' => 'Waiting for the trainer\'s assessment.',
        'enrollment' => 'Open the trainee account',
        'family_reads' => 'What the trainee reads',
        'academy_notes' => 'The trainer\'s notes for the academy',
        'report_sent' => 'The report reached the trainee :when',
        'report_not_sent' => 'The report has not been sent to the trainee yet',
        'resend_heading' => 'Send the report to the trainee again?',
        'resend_body' => 'They get a new link by WhatsApp and email. The report shows the assessment as it is now.',
        'offer' => [
            'reminded' => 'the trainee was reminded',
        ],
        'follows' => 'Follows the introductory session of :when',
    ],
    'activity' => [
        'report_sent' => 'The introductory session\'s report reached the trainee',
        'report_resent' => 'The introductory session\'s report was sent to the trainee again',
        'second_trial' => 'Booked the free second introductory session with another trainer',
        'rebooked' => 'Booked a new time after a session that did not take place',
        'trial_booked' => 'Introductory session booked with :teacher on :when',
        'trial_moved' => 'Introductory session moved to :when',
        'trial_cancelled' => 'Introductory session cancelled',
        'trial_attended' => 'Attended the introductory session',
        'trial_missed' => 'The introductory session did not take place: :outcome',
        'assessed' => 'Assessed by the trainer: :level',
        'booked_online' => "Booked the introductory session themselves on the academy's page",
    ],
    'actions' => [
        'book_trial' => 'Book an introductory session',
    ],
    'errors' => [
        'booking_off' => 'Free introductory sessions are not booked from the page right now. Send us your request and we will be in touch.',
        'trial_open' => 'You already have an introductory session booked in this area; its time is in our WhatsApp message, and you can change it from the link there.',
        'too_many_open' => 'This number already has :count introductory sessions booked that have not taken place yet. Please wait until one of them does, or message us and we will book it for you.',
        'number_in_use' => 'This number is registered with us in a way that cannot book from here. Please message the academy on WhatsApp and we will book the session for you.',
        'already_student' => 'You are already enrolled with us in this area. Sign in to follow your sessions, or choose another area for the introductory session.',
        'had_trial' => 'You have already had your free introductory session in this area. Message us and we will help you choose the right programme, or choose another area.',
        'no_subject' => 'Choose an area.',
        'other_learner' => 'We have another request with this number for someone else. Send your request without a time and we will be in touch to book it.',
    ],
];
