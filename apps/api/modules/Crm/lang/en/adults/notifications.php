<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// No guardians there: what went to the family reaches the trainee, written to them.
return [
    'audience' => [
        'teacher' => 'The introductory session\'s trainer',
        'family' => 'The trainee',
    ],
    'assess_trial' => ['title' => 'Assess the introductory session', 'body' => ':student · :subject · :when. Write the level and the plan you suggest so sales can follow up.'],
    'trial_missed' => ['title' => 'The introductory session did not take place', 'body' => ':name: :outcome. Call them and book another time.'],
    'trial_assessed' => ['title' => ':name\'s assessment is ready', 'body' => ':teacher: :level. Time to talk to them about subscribing.'],
    'trial_unassessed' => ['title' => ':name\'s assessment is not written yet', 'body' => 'Their introductory session with :teacher was a day ago and has no assessment. Remind the trainer: the trainee is waiting for the next step.'],
    'trial_cancelled' => ['title' => ':name cancelled their introductory session', 'body' => 'It was on :when. Get in touch with them and book another time.'],
    'trial_report_ready' => ['title' => 'Your introductory session report is ready', 'body' => ':teacher wrote about your :subject introductory session: your level, strengths, what to work on, and the plan they suggest. Read it here: :link :offer'],
    'trial_offer_ending' => ['title' => 'Your offer ends soon', 'body' => ':percent% off your first plan ends on :until. The details and how to start are on the report: :link'],
    'trial_missed_family' => ['title' => 'We missed you at the introductory session', 'body' => 'You did not come to the :subject introductory session on :when. :next'],
    'trial_sorry_family' => ['title' => 'We are sorry about your introductory session', 'body' => 'We could not hold your :subject introductory session on :when, and we apologise. It does not count as your free introductory session. :next'],
];
