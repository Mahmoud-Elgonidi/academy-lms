<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../policy.php.
return [
    'sections' => [
        'student' => 'Trainee cancellations and reschedules',
        'teacher' => 'Trainer cancellations and pay',
    ],
    'fields' => [
        'student_notice_hours' => 'Trainee notice for cancelling or moving (hours)',
        'teacher_notice_hours' => 'Trainer notice for cancelling (hours)',
        'late_cancel_teacher_pay_pct' => 'Trainer pay when the trainee cancels late (%)',
        'student_absent_teacher_pay_pct' => 'Trainer pay when the trainee is absent (%)',
        'teacher_late_after_min' => 'Trainer late after (minutes)',
        'teacher_absent_after_min' => 'Trainer absent after (minutes)',
        'student_wait_short_min' => 'Wait for the trainee, 30-minute sessions (minutes)',
        'student_wait_long_min' => 'Wait for the trainee, 45 minutes or longer (minutes)',
        'completion_pct' => 'A session counts as completed when delivered (%)',
    ],
    'hints' => [
        'unpaid_cancel_hours' => 'Enrolled trainees only. 0 turns automatic cancellation off.',
    ],
    'rules' => [
        'credit' => 'Trainee credit',
        'teacher_pay' => 'Trainer pay',
    ],
    'events' => [
        'student_reschedule' => 'Trainee reschedules',
        'student_cancel' => 'Trainee cancels',
        'teacher_cancel' => 'Trainer cancels',
        'student_absent' => 'Trainee absent',
        'teacher_absent' => 'Trainer absent',
        'course_stopped' => 'Programme paused or ended',
    ],
    'kpi' => [
        'late_student_cancel' => 'Late cancellation by the trainee',
        'student_absent' => 'Trainee absence',
        'teacher_cancel' => 'Trainer cancellation',
        'teacher_late_cancel' => 'Late trainer cancellation',
        'teacher_absent' => 'Trainer absence',
    ],
    'asked_by' => [
        'student' => 'Trainee',
        'teacher' => 'Trainer',
        'family' => 'The account holder',
    ],
    'reasons' => [
        'school' => 'Work or exams',
        'family' => 'Personal matter',
        'teacher_leave' => 'Trainer on leave',
        'substitute_refused' => 'Declined the substitute trainer',
        'enrollment_paused' => 'Programme paused',
        'enrollment_ended' => 'Programme ended',
    ],
    'preview' => [
        'credit' => [
            'refund' => 'The minutes go back to the trainee\'s credit.',
            'forfeit' => 'The trainee loses the session (it is charged).',
            'makeup' => 'The trainee gets makeup credit valid :days days.',
        ],
        'teacher_pay' => 'Trainer pay: :pct%',
    ],
    'refusals' => [
        'quota' => 'The trainee has used the :n cancellations and reschedules of this block.',
    ],
    'errors' => [
        'not_cleared' => 'This trainer cannot take new sessions right now (:state).',
        'teacher_busy' => 'The trainer is not free or not on shift then.',
        'someone_busy' => 'The trainer or the trainee has another session then.',
        'no_extra_credit' => 'The programme has not enough paid minutes valid on that day (makeup credit is for makeups).',
        'course_not_running' => 'This programme has no sessions now (not started, paused or ended).',
        'same_teacher' => 'That is the current trainer.',
        'new_teacher_busy' => 'The new trainer is not free on: :dates',
    ],
];
