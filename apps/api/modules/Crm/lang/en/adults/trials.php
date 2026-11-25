<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../trials.php.
// The trainer writes the report to the trainee themself.
return [
    'nav' => 'Introductory sessions',
    'title' => 'Introductory sessions',
    'subtitle' => 'After each introductory session, write your report: what the trainee reads in the session\'s report, and your notes for the academy alone.',
    'empty' => [
        'to_assess' => 'No sessions waiting for an assessment.',
        'upcoming' => 'No introductory sessions coming up.',
    ],
    'assess_hint' => 'The first part reaches the trainee in the session\'s report, so write it to them clearly and kindly. Your notes in the last part are for the academy alone: the trainee never sees them.',
    'sections' => [
        'family' => 'For the trainee: shown in the session\'s report',
        'academy' => 'For the academy only: the trainee does not see it',
    ],
    'family_reads' => 'The trainee reads this',
    'fields' => [
        'per_week' => 'Sessions a week',
        'family_note' => 'Your words to the trainee',
    ],
    'strengths_hint' => 'For example: explains their ideas clearly, likes to ask questions.',
    'to_work_on_hint' => 'For example: the technical terms, focus at the end of the session.',
    'family_note_hint' => 'A short word that encourages the trainee and shows them the next step.',
    'level_hint' => 'For example: beginner, knows the basics and needs practice',
    'notes_hint' => 'What sales should know that is not for the trainee.',
    'outcomes' => [
        'student_absent' => 'the trainee did not come',
        'teacher_absent' => 'the trainer did not come',
        'family' => 'cancelled by the trainee',
    ],
    'kinds' => [
        'first' => 'The free introductory session',
        'second' => 'The free second introductory session with another trainer',
    ],
    'errors' => [
        'not_yours' => 'This is not your session.',
        'not_attended' => 'This session has not taken place.',
        'no_report' => 'This session has no report yet: it is written once the session took place and the trainer assessed it.',
    ],
];
