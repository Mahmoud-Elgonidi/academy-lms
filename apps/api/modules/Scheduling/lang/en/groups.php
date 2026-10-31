<?php

// Group lessons (docs/architecture/02, "الحصص الجماعية").
return [
    'format' => [
        'private' => 'Private',
        'group' => 'Group',
    ],
    'statuses' => [
        'forming' => 'Waiting for students',
        'running' => 'Running',
        'ended' => 'Ended',
    ],
    'late_join' => [
        'discount' => 'Discount on the first invoice',
        'private' => 'Private makeup lessons with the teacher',
        'group' => 'Makeup lessons in another group',
        'none' => 'No compensation',
    ],
    'errors' => [
        'no_groups' => 'This teacher does not teach groups.',
        'capacity_range' => 'A group has 2 to 20 seats.',
        'over_teacher' => 'This teacher takes at most :max students in a group.',
        'below_members' => 'The group has :count students; it cannot have fewer seats.',
        'min_to_start' => 'The students a group waits for is 1 to :capacity.',
        'age_group' => 'Unknown age group.',
        'session_minutes' => 'A lesson is 1 to 240 minutes.',
        'bad_time' => 'Pick a valid day and time for each weekly time.',
        'time_twice' => 'Each weekly time once.',
        'no_times' => 'Pick at least one time a week.',
        'ended' => 'This group has ended.',
        'already' => 'The student is in this group.',
        'full' => 'The group is full: :capacity seats taken.',
        'plan' => 'Pick an active plan.',
        'plan_not_group' => 'A seat in a group needs a group plan.',
        'plan_shape' => 'The group has :minutes-minute lessons, :per_week a week; pick a plan of that shape.',
        'plan_is_group' => 'This is a group plan; private lessons need a private plan.',
        'age_band' => 'This group is for :age.',
        'minors_adults' => 'Students under 18 and adults do not share a group.',
        'level' => "The student's level is not the group's.",
        'late_join' => 'Pick how the missed lessons are made up for.',
        'no_group_makeup' => 'No makeup credit for group lessons.',
        'not_group_lesson' => 'This is not a group lesson.',
        'lesson_closed' => 'This lesson is no longer open.',
        'other_subject' => 'This lesson is for another subject.',
        'own_group' => "This is the student's own group lesson.",
        'student_busy' => 'The student has another lesson at that time.',
    ],
];
