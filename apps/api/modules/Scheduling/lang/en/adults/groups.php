<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'statuses' => [
        'forming' => 'Waiting for trainees',
    ],
    'late_join' => [
        'private' => 'Private makeup sessions with the trainer',
        'group' => 'Makeup sessions in another group',
    ],
    'errors' => [
        'no_groups' => 'This trainer does not train groups.',
        'over_teacher' => 'This trainer takes at most :max trainees in a group.',
        'below_members' => 'The group has :count trainees; it cannot have fewer seats.',
        'min_to_start' => 'The trainees a group waits for is 1 to :capacity.',
        'session_minutes' => 'A session is 1 to 240 minutes.',
        'already' => 'The trainee is in this group.',
        'plan_shape' => 'The group has :minutes-minute sessions, :per_week a week; pick a plan of that shape.',
        'plan_is_group' => 'This is a group plan; private sessions need a private plan.',
        'minors_adults' => 'This group does not mix age groups.',
        'level' => "The trainee's level is not the group's.",
        'late_join' => 'Pick how the missed sessions are made up for.',
        'no_group_makeup' => 'No makeup credit for group sessions.',
        'not_group_lesson' => 'This is not a group session.',
        'lesson_closed' => 'This session is no longer open.',
        'other_subject' => 'This session is for another area.',
        'own_group' => "This is the trainee's own group session.",
        'student_busy' => 'The trainee has another session at that time.',
    ],
];
