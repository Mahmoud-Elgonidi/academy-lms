<?php

// Group lessons, each student's outcome (docs/architecture/02, "الحصص الجماعية").
return [
    'format' => [
        'private' => 'Private',
        'group' => 'Group',
    ],
    'outcomes' => [
        'attended' => 'Attended',
        'absent' => 'Absent',
        'excused' => 'Excused',
        'makeup' => 'Makeup owed',
    ],
    // The lesson as a group, where one line names it: "Group · 3 students".
    'label' => 'Group · :count students',
    // The kids' look: classmates as a number, never names.
    'with_classmates' => '{0} On your own today|{1} With 1 classmate|[2,*] With :count classmates',
    'excuse' => [
        'refund' => 'The lesson goes on for the group; your seat is excused and the lesson comes back to your balance (at least :hours hours before it).',
        'makeup' => 'The lesson goes on for the group; your seat is excused and you get a makeup lesson.',
        'late' => 'The lesson goes on for the group; your seat is excused, but less than :hours hours before it the lesson counts as taken.',
        'quota' => 'The lesson goes on for the group; your seat is excused, but you have used the :quota changes of this package, so it counts as taken.',
    ],
    'errors' => [
        'no_seat' => 'This student has no seat in this lesson.',
        'outcome' => 'Unknown outcome for a seat.',
        'whole_group' => 'A group lesson moves only for the whole group, by the academy. For one student, book a makeup lesson.',
        'teacher_no_groups' => 'This teacher does not teach groups.',
        'teacher_too_small' => 'This teacher takes at most :max students in a group; this one has :count.',
        'report_students' => 'Write a row for each student in the lesson.',
    ],
    'notifications' => [
        'group_started_family' => [
            'title' => 'The group starts',
            'body' => ':student\'s group for :subject with :teacher has started. The first lesson: :when.',
        ],
        'group_started_teacher' => [
            'title' => 'Your group starts',
            'body' => 'Your :subject group has its students (:count) and has started. The first lesson: :when.',
        ],
    ],
];
