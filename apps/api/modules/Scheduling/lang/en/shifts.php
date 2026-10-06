<?php

return [
    'arrow' => '→',
    'days' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
    'roles' => ['teacher' => 'the teacher', 'admin' => 'the admins', 'system' => 'the system'],
    'statuses' => [
        'draft' => 'Draft',
        'submitted' => 'Under review',
        'changes_requested' => 'Changes requested',
        'countered' => 'Counter-offer from admins',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
        'superseded' => 'Superseded',
    ],
    'problems' => [
        'empty' => 'The schedule is empty: add at least one block.',
        'block_too_short' => 'The block starting :start on :day is shorter than :min minutes.',
        'blocks_overlap' => 'Two blocks overlap on :day (at :start).',
        'range_inverted' => 'The minimum hours are above the maximum.',
        'above_own_max' => 'The schedule is :hours hours a week, above the maximum you set (:max).',
        'above_contract' => 'The schedule is :hours hours a week, above the contract limit (:max).',
        'notice_needed' => 'It removes time, so it can start :days days from now at the earliest (:date).',
        'starts_tomorrow_at_earliest' => 'The earliest start date is :date.',
    ],
    'errors' => [
        'already_sent' => 'This proposal was sent; edits now go as a new revision.',
        'reason_required' => 'Write the reason so the teacher knows.',
        'not_now' => 'That is not possible in the proposal\'s current state.',
        'teacher_drafting' => 'The teacher is drafting a proposal; wait until it is sent.',
        'cannot_approve' => 'This needs the other side\'s agreement or a written override reason.',
        'unresolved' => ':count conflict(s) are not resolved yet.',
        'conflict_gone' => 'That conflict no longer exists.',
        'comment' => 'Write a message (up to 2000 characters).',
    ],
    'comments' => [
        'approved' => 'Schedule approved, starting :date (:by).',
        'override' => 'override: :reason',
        'delayed' => 'Start moved to :date so the booked sessions finish first.',
        'withdrawn' => 'The teacher withdrew the proposal.',
    ],
];
