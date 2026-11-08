<?php

// The academy's AI helpers: their names, and why one gave nothing back.
return [
    'features' => [
        'practice_items' => 'Practice suggestions for a lesson',
        'report_draft' => 'Lesson report from a voice note',
        'recitation_check' => 'Recitation check',
        'digest_wording' => 'Wording of the weekly note to guardians',
        'curriculum_outline' => 'Curriculum outline',
    ],
    'about' => [
        'practice_items' => 'In the curriculum builder: a button that suggests practice questions for a lesson (choice, order, pairs) for you to review before saving.',
        'report_draft' => 'After a lesson the teacher records a short voice note and the helper fills in the report as a draft, which the teacher checks and sends.',
        'recitation_check' => 'When a student hands in a recitation, the helper compares it with the expected text and marks missing or wrong words, for the teacher only.',
        'digest_wording' => 'The weekly note to guardians is written warmly from the week\'s facts, instead of the fixed list.',
        'curriculum_outline' => 'In the curriculum builder: an outline of levels, units and lessons from the goal you type, which you review and edit before it is saved.',
    ],
    'failed' => [
        'off' => 'This helper is off at the academy.',
        'limit' => 'The helper has been used up for today. Try again tomorrow.',
        'quota' => 'The academy\'s AI credits are used up. Carry on by hand; the helper comes back when the plan\'s credits start again on the first of the month, or once extra credits the academy\'s owner or an admin asks for on "Usage and balance" are turned on.',
        'quota_needed' => '{1} The academy\'s AI credits are not enough for this: it needs one credit. Carry on by hand; the helper comes back when the plan\'s credits start again on the first of the month, or once extra credits the academy\'s owner or an admin asks for on "Usage and balance" are turned on.|[2,*] The academy\'s AI credits are not enough for this: it needs :n credits. Carry on by hand; the helper comes back when the plan\'s credits start again on the first of the month, or once extra credits the academy\'s owner or an admin asks for on "Usage and balance" are turned on.',
        'busy' => 'The helper is busy right now. Try again in a minute.',
        'not_configured' => 'The helper is not set up on the platform yet.',
        'unavailable' => 'The helper is not available right now. Try again soon.',
        'refused' => 'The helper could not write anything for this.',
        'too_long' => 'This is longer than the helper can take.',
        'unreadable' => 'The helper answered with something unclear. Try again.',
        'unheard' => 'We could not make out the recording. Record it again, more clearly.',
    ],
    'suggested' => 'Suggested',
];
