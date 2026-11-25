<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'zone_chip' => 'Each trainer\'s own clock',
    'tab_groups' => [
        'teacher' => 'With the trainer',
    ],
    'empty_teachers' => 'No trainers yet.',
    'groups' => [
        'teacher' => 'With the trainer',
    ],
    'group_hints' => [
        'teacher' => 'Waiting for the trainer to reply.',
    ],
    'empty' => [
        'teacher' => 'No proposals are with trainers.',
    ],
    'tabs' => ['teachers' => 'Trainers\' schedules'],
    'teacher_schedule' => 'Trainer\'s schedule',
    'roles' => ['teacher' => 'Trainer'],
    'events' => [
        'submitted' => 'The trainer sent the proposal',
    ],
    'steps' => ['impact' => 'Booked trainees'],
    'teachers_hint' => 'Each trainer\'s schedule in force today and any approved to start later. Edit sends a counter-offer that starts from the newest one.',
    'no_teachers' => 'No trainers yet.',
    'impact' => 'Impact on trainees',
    'impact_title' => 'Impact on booked trainees',
    'resolutions' => [
        'moved' => 'Move to another trainer',
    ],
    'resolution_hints' => [
        'exception' => 'The slot stays with this trainer outside the shift, agreed by both.',
        'moved' => 'Opens the trainee\'s account to move them to a trainer free at the same times from the start date. Once moved, the conflict goes away.',
    ],
    'move_unenrolled' => 'These sessions are not part of an enrolment, so they cannot be moved from here.',
    'reply_placeholder' => 'Your reply to the trainer',
    'reply_hint' => 'The trainer sees it in the app.',
    'waiting_teacher' => 'The counter-offer is with the trainer; it applies once they accept.',
    'override_hint' => 'Approving without the trainer\'s agreement or over open conflicts is recorded with your name and this reason, and the trainer is told.',
    'counter_hint' => 'Edit the times on :city time and send them; the trainer accepts with one tap or edits again.',
    'note_to_teacher' => 'Message to the trainer (optional)',
    'effective_from_hint' => 'It can start today. Booked sessions are kept; any outside the new hours shows as a conflict to resolve.',
    'teacher' => 'Trainer',
    'edit_teacher' => 'Edit a trainer\'s schedule',
    'edit_teacher_hint' => 'Sent to the trainer as a counter-offer to accept, unless the academy is "admin only".',
    'done' => [
        'rejected' => 'Proposal rejected; the trainer will see why.',
        'changes_requested' => 'Change request sent to the trainer.',
        'countered' => 'Counter-offer sent to the trainer.',
    ],
];
