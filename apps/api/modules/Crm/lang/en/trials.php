<?php

return [
    'nav' => 'Trial lessons',
    'title' => 'Trial lessons',
    'subtitle' => 'After each trial lesson, write the level you found and the plan you suggest. Sales talk to the family with it.',
    'groups' => [
        'to_assess' => 'Waiting for your assessment',
        'upcoming' => 'Coming up',
        'done' => 'Assessed',
    ],
    'empty' => [
        'to_assess' => 'No lessons waiting for an assessment.',
        'upcoming' => 'No trial lessons coming up.',
        'done' => 'None yet.',
    ],
    'age' => ':years years old',
    'notes_from_sales' => 'From sales: :notes',
    'assess' => 'Assess',
    'reassess' => 'Edit the assessment',
    'assess_heading' => 'Assessing :name',
    'assess_hint' => 'The family does not see this. Sales will talk to them.',
    'assessed' => 'Assessment sent. Thank you!',
    'fields' => [
        'level' => 'The level you found',
        'plan_id' => 'The plan you suggest',
        'per_week' => 'Lessons a week',
        'notes' => 'Your notes for the academy',
    ],
    'level_hint' => 'For example: beginner, reads letters without vowels',
    'notes_hint' => 'Strengths, what needs work, and anything sales should know.',
    'statuses' => [
        'booked' => 'Booked',
        'attended' => 'Attended',
        'missed' => 'Did not take place',
        'cancelled' => 'Cancelled',
    ],
    'outcomes' => [
        'completed' => 'attended',
        'student_absent' => 'the student did not come',
        'teacher_absent' => 'the teacher did not come',
        'needs_review' => 'under review',
        'cancelled' => 'cancelled',
    ],
    'errors' => [
        'not_yours' => 'This is not your lesson.',
        'not_attended' => 'This lesson has not taken place.',
    ],
];
