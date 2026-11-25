<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'label' => 'Group · :count trainees',
    'with_classmates' => '{0} On your own today|{1} With 1 fellow trainee|[2,*] With :count fellow trainees',
    'course' => [
        'forming' => 'Forming: sessions start once it has its trainees',
        'waits' => '{1} Forming: waits for 1 more trainee to start|[2,*] Forming: waits for :n more trainees to start',
    ],
    'conflict' => '{1} Group · 1 trainee|[2,*] Group · :count trainees',
    'conflict_times' => 'on each trainee\'s clock',
    'board' => [
        'student' => 'Trainee who will not attend',
        'student_hint' => 'Only this trainee\'s seat is excused; the session goes on for the rest of the group.',
        'none_open' => 'No seat in this session can be excused.',
        'pick' => 'Choose the trainee to see what the policy does to their credit.',
    ],
    'excuse' => [
        'button' => 'I won\'t attend this session',
        'heading' => 'Not attending this session?',
        'done' => 'You are excused from this session; it goes on for the rest of the group.',
        'refund' => 'The session goes on for the group; your seat is excused and the session comes back to your balance (at least :hours hours before it).',
        'makeup' => 'The session goes on for the group; your seat is excused and you get a makeup session.',
        'late' => 'The session goes on for the group; your seat is excused, but less than :hours hours before it the session counts as taken.',
        'quota' => 'The session goes on for the group; your seat is excused, but you have used the :quota changes of this package, so it counts as taken.',
    ],
    'errors' => [
        'no_seat' => 'This trainee has no seat in this session.',
        'whole_group' => 'A group session moves only for the whole group, by the academy. For one trainee, book a makeup session.',
        'teacher_no_groups' => 'This trainer does not train groups.',
        'teacher_too_small' => 'This trainer takes at most :max trainees in a group; this one has :count.',
        'report_students' => 'Write a row for each trainee in the session.',
        'pick_seat' => 'Choose the trainee who will not attend: a group session is never cancelled for a trainee, only their own seat is excused.',
        'no_open_seat' => 'No seat in this session can be excused: every trainee is already excused or decided.',
    ],
    'notifications' => [
        'group_started_family' => [
            'body' => 'Your :subject group with :teacher has started. The first session: :when.',
        ],
        'group_started_teacher' => [
            'body' => 'Your :subject group has its trainees (:count) and has started. The first session: :when.',
        ],
    ],
];
