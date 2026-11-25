<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../timeoff.php.
return [
    'lessons' => '{0} no sessions|{1} one session|[2,*] :count sessions',
    'form' => [
        'teacher' => 'Trainer',
        'impact' => 'Sessions in that time',
        'impact_none' => 'No sessions are booked in that time.',
    ],
    'errors' => [
        'not_coverable' => 'This session has started or closed.',
        'not_their_subject' => 'That trainer does not train in this area.',
        'no_substitute' => 'This session is not with a substitute.',
    ],
    'mine' => [
        'subtitle' => 'Ask for time off; the academy approves it and arranges cover for your sessions. Dates on your clock (:zone).',
    ],
    'admin' => [
        'nav' => 'Trainer time off',
        'title' => 'Trainer time off',
        'subtitle' => 'Approve requests, and give each session in the leave a substitute or a makeup. A session nobody covered is cancelled by itself an hour before, with a makeup for the trainee.',
        'empty' => [
            'cover' => 'Every session is covered.',
        ],
        'left' => '{1} one session needs cover|[2,*] :count sessions need cover',
    ],
    'review' => [
        'note' => 'Note to the trainer',
        'call_off_hint' => 'The trainer takes back the sessions still with them. Sessions given to a substitute stay with them.',
        'cover_all' => 'Cover every session',
        'cover_all_hint' => 'Each session goes to the best free trainer in the area (who trained the trainee before first, then the least busy).',
        'teacher_time' => 'For the trainer :time (:zone)',
        'teacher_zone' => 'On the trainer\'s clock (:zone)',
        'lessons' => 'Sessions in the time off',
        'no_lessons' => 'No sessions in that time.',
        'pick' => 'Choose a trainer',
        'taught' => 'Trained the trainee :count times',
        'no_candidates' => 'No trainer in this area is free then.',
        'assigned' => 'The session is now with :name.',
        'makeup_confirm' => 'The session will be cancelled and the trainee gets makeup credit. Sure?',
        'made_up' => 'Cancelled; the trainee has makeup credit.',
        'local' => ':time for the trainer',
    ],
    'holidays' => [
        'subtitle' => 'Eid and public holidays, as days on the academy\'s clock (:zone). Sessions on those days are cancelled with their minutes back, the trainee\'s credit lasts as many days longer, and no new sessions are made there.',
        'impact_none' => 'No sessions are booked on those days.',
        'impact_some' => ':lessons for :students will be cancelled, the minutes back in their balance and its validity :days longer. Each trainee and trainer gets one message.',
        'students' => '{1} one trainee|[2,*] :count trainees',
        'remove_confirm' => 'Remove the holiday? Sessions it cancelled do not come back by themselves, but new sessions will be made on those days again.',
    ],
    'family' => [
        'refuse' => "I don't want the substitute",
        'refuse_text' => 'The session will be cancelled and you get makeup credit valid :days days, to book a session with your trainer once they are back.',
        'refused' => 'The session was cancelled; you have makeup credit.',
        'holiday' => 'The academy is closed (:name) :period: no sessions on those days.',
        'why' => [
            'teacher_leave' => 'The trainer is on leave; a makeup is owed',
            'enrollment_paused' => 'The programme is paused; the minutes are back in the balance',
            'enrollment_ended' => 'The programme ended; the minutes are back in the balance',
        ],
    ],
];
