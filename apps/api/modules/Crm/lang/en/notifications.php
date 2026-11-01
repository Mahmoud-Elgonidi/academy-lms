<?php

return [
    'audience' => [
        'sales' => 'The lead\'s owner (or the sales team)',
        'teacher' => 'The trial lesson\'s teacher',
        'family' => 'The family (the parent, or the adult student)',
    ],
    'lead_assigned' => ['title' => 'A new lead for you: :name', 'body' => 'From :source. Open it and get in touch.'],
    'assess_trial' => ['title' => 'Assess the trial lesson', 'body' => ':student · :subject · :when. Write the level and the plan you suggest so sales can follow up.'],
    'trial_missed' => ['title' => 'The trial lesson did not take place', 'body' => ':name: :outcome. Call them and book another time.'],
    'trial_assessed' => ['title' => ':name\'s assessment is ready', 'body' => ':teacher: :level. Time to talk to the family about subscribing.'],
    'lead_waiting' => ['title' => 'Nobody has been in touch with :name yet', 'body' => 'Their request is :hours hours old with no contact, and the academy promised to answer within that. Get in touch now.'],
    'trial_unassessed' => ['title' => ':name\'s assessment is not written yet', 'body' => 'Their trial with :teacher was a day ago and has no assessment. Remind the teacher: the family is waiting for the next step.'],
    'trial_cancelled' => ['title' => 'The family cancelled :name\'s trial', 'body' => 'It was on :when. Get in touch with the family and book another time.'],
    // After the trial (J2): to the family.
    'trial_report_ready' => ['title' => ':student\'s free trial report is ready', 'body' => ':teacher wrote about :student\'s :subject trial: the level, strengths, what to work on, and the plan they suggest. Read it here: :link :offer'],
    'offer_line' => 'It comes with an offer: :percent% off the first plan, for a limited time shown on the report.',
    'trial_offer_ending' => ['title' => 'Your offer ends soon', 'body' => ':percent% off :student\'s first plan ends on :until. The details and how to start are on the report: :link'],
    'trial_missed_family' => ['title' => 'We missed :student at the free trial', 'body' => ':student did not come to the :subject trial on :when. :next'],
    'trial_sorry_family' => ['title' => 'We are sorry about :student\'s free trial', 'body' => 'We could not hold :student\'s :subject trial on :when, and we apologise. It does not count as your free trial. :next'],
    'next' => [
        'link' => 'Choose another time that suits you here: :link',
        'call' => 'We will contact you to agree another time.',
    ],
];
