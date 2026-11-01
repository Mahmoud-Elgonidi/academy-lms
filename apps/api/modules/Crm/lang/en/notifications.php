<?php

return [
    'audience' => [
        'sales' => 'The lead\'s owner (or the sales team)',
        'teacher' => 'The trial lesson\'s teacher',
    ],
    'lead_assigned' => ['title' => 'A new lead for you: :name', 'body' => 'From :source. Open it and get in touch.'],
    'assess_trial' => ['title' => 'Assess the trial lesson', 'body' => ':student · :subject · :when. Write the level and the plan you suggest so sales can follow up.'],
    'trial_missed' => ['title' => 'The trial lesson did not take place', 'body' => ':name: :outcome. Call them and book another time.'],
    'trial_assessed' => ['title' => ':name\'s assessment is ready', 'body' => ':teacher: :level. Time to talk to the family about subscribing.'],
    'lead_waiting' => ['title' => 'Nobody has been in touch with :name yet', 'body' => 'Their request is :hours hours old with no contact, and the academy promised to answer within that. Get in touch now.'],
    'trial_unassessed' => ['title' => ':name\'s assessment is not written yet', 'body' => 'Their trial with :teacher was a day ago and has no assessment. Remind the teacher: the family is waiting for the next step.'],
    'trial_cancelled' => ['title' => 'The family cancelled :name\'s trial', 'body' => 'It was on :when. Get in touch with the family and book another time.'],
];
