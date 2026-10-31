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
    // How the family and the teacher read a group lesson in /my and /teach.
    'family' => [
        'names' => 'With :names',
        'seat' => 'Your seat: :outcome',
        'teacher_badge' => 'Group · :taken/:capacity',
    ],
    // The group's card above the learner's lessons in /my.
    'course' => [
        'heading' => 'Your groups',
        'title' => ':subject group',
        'seat' => 'seat :seat of :capacity',
        'forming' => 'Forming: lessons start once it has its students',
        'waits' => '{1} Forming: waits for 1 more student to start|[2,*] Forming: waits for :n more students to start',
    ],
    // A group in the teacher's messages and shift conflicts: by its name (subject and times), never its first student's.
    'teacher_name' => ':subject group (:times)',
    'conflict' => '{1} Group · 1 student|[2,*] Group · :count students',
    'conflict_times' => 'on each student\'s clock',
    // The live board: excusing one student's seat in a group lesson.
    'board' => [
        'student' => 'Student who will not attend',
        'student_hint' => 'Only this student\'s seat is excused; the lesson goes on for the rest of the group.',
        'none_open' => 'No seat in this lesson can be excused.',
        'pick' => 'Choose the student to see what the policy does to their credit.',
    ],
    'excuse' => [
        'button' => 'I won\'t attend this lesson',
        'heading' => 'Not attending this lesson?',
        'confirm' => 'Yes, excuse me',
        'done' => 'You are excused from this lesson; it goes on for the rest of the group.',
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
        'pick_seat' => 'Choose the student who will not attend: a group lesson is never cancelled for a student, only their own seat is excused.',
        'no_open_seat' => 'No seat in this lesson can be excused: every student is already excused or decided.',
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
