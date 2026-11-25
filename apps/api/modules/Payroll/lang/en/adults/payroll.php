<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../payroll.php.
// Trainers' pay: the teacher is the trainer and the lesson a session.
return [
    'subtitle' => 'Each session\'s pay is recorded when the session closes, from the trainer\'s rate card. Close a month once it is over; its numbers then never change.',
    'tiles' => ['teachers' => 'Trainers', 'lessons' => 'Sessions'],
    'columns' => [
        'teacher' => 'Trainer',
        'lessons' => 'Sessions',
        'lesson_pay' => 'Session pay',
    ],
    'empty' => [
        'hint' => 'Pay is recorded when a session closes. If sessions took place and are missing here, check they have a price under "Trainer rates".',
    ],
    'unpriced' => [
        'text' => '{1} One session took place with no price on its trainer\'s rate card.|[2,*] :count sessions took place with no price on their trainer\'s rate card.',
        'rates' => 'Trainer rates',
    ],
    'pending' => '{1} One session this month is still waiting for a decision (under review or not closed yet). Once decided, its pay goes into the first open month.|[2,*] :count sessions this month are still waiting for a decision (under review or not closed yet). Once decided, their pay goes into the first open month.',
    'actions' => [
        'recalc_hint' => 'Every session this month is priced again from today\'s rate cards. Any difference is recorded as a correction; earlier lines are never deleted.',
        'close_hint' => 'Once closed, :month\'s numbers never change; sessions decided or corrected later are paid in the first open month. Each trainer is told their total.',
        'close_pending' => ':count sessions are still waiting for a decision.',
        'close_unpriced' => ':count sessions have no price.',
        'recalculated' => '{0} No sessions changed.|{1} One session priced.|[2,*] :count sessions priced.',
    ],
    'fields' => [
        'teacher' => 'Trainer',
        'note_hint' => 'The trainer sees this reason on their statement.',
    ],
    'kinds' => ['lesson' => 'Session'],
    'outcomes' => [
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
    ],
    'units' => ['lesson' => ':amount / session'],
    'share_lesson' => ':pct of the session rate',
    'statement' => [
        'lessons' => 'Sessions',
        'no_lessons' => 'No paid sessions this month.',
        'earlier' => 'Session of :month',
        'reverse_hint' => 'A reversal is recorded in the first open month, and the trainer sees it.',
        'back' => 'All trainers',
    ],
    'errors' => [
        'no_card' => 'This trainer has no rate card and there is no default one. Add one under "Trainer rates".',
        'not_teacher' => 'Not a trainer.',
    ],
    'mine' => [
        'subtitle' => 'Your pay for each session given, from your rate card. Once a month is over it is closed and its numbers are final.',
        'open_note' => 'This month is still open; numbers may change if a session is reviewed.',
        'unpriced' => '{1} One session has no price on your card yet; the academy will price it.|[2,*] :count sessions have no price on your card yet; the academy will price them.',
        'any' => 'Any session',
    ],
    'export' => [
        'lessons_sheet' => 'Sessions',
        'teacher' => 'Trainer',
        'lessons' => 'Sessions',
        'lesson_pay' => 'Session pay',
        'student' => 'Trainee',
        'subject' => 'Area',
        'units' => ['lesson' => 'session'],
    ],
];
