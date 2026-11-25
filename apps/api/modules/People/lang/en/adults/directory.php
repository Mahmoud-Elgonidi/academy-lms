<?php

// An academy that trains adults only (docs/architecture/02, section 29): only the keys whose words differ.
return [
    'teachers' => [
        'nav' => 'Trainers',
        'model' => 'trainer',
        'subtitle' => 'Each trainer with their fields and levels, and their weekly and daily caps. "Find a trainer" searches by them.',
        'create' => 'New trainer',
        'created' => 'Trainer added',
        'empty' => 'No trainers yet',
        'empty_hint' => 'Add a trainer here, or import them from a sheet.',
    ],
    'students' => [
        'nav' => 'Trainees',
        'model' => 'trainee',
        'subtitle' => 'Every trainee signs in themselves with their own email or number. Enrol them in a programme from "Find a trainer".',
        'create' => 'New trainee',
        'created' => 'Trainee added',
        'empty' => 'No trainees yet',
        'empty_hint' => 'Add a trainee here, or import your trainees from a sheet.',
    ],
    'sections' => [
        'teaching' => 'Training',
        'subjects' => 'Fields',
        'student' => 'About the trainee',
    ],
    'fields' => [
        'accepts_new' => 'Takes new trainees',
        'accepts_trials' => 'Gives trial sessions',
        'accepts_groups' => 'Trains groups',
        'accepts_groups_hint' => 'Group sessions beside the private ones: several trainees of the same level in one session, on a group plan.',
        'max_group_size' => 'Most trainees in a group session',
        'group_up_to' => '{2} Up to 2 trainees a session|[3,*] Up to :n trainees a session',
        'groups_note' => 'Their times show in "Find a trainer" in group mode, where groups are made.',
        'max_daily_lessons' => 'Most sessions a day',
        'max_daily_hint' => '"Find a trainer" suggests no day on which they reached it.',
        'subjects' => 'Fields they train in',
        'subject' => 'Field',
        'categories_hint' => 'None chosen: all of the field\'s categories.',
        'add_subject' => 'Add a field',
        'welcome_hint' => 'On WhatsApp and email.',
        'teacher_gender' => 'Trainer\'s gender',
        'teacher_gender_hint' => '"Find a trainer" starts from it.',
        'student' => 'Trainee',
    ],
    'options' => [
        'student_genders' => ['female' => 'Woman', 'male' => 'Man'],
    ],
    'columns' => [
        'subjects' => 'Fields',
        'accepts_new' => 'New trainees',
        'no_groups' => 'Trains no groups',
    ],
    'filters' => ['subject' => 'Field', 'groups_yes' => 'Trains groups', 'groups_no' => 'Trains no groups'],
    'actions' => [
        'calendar' => 'Sessions on the calendar',
    ],
    'errors' => [
        'child_needs_guardian' => 'Add the trainee\'s email or number: every trainee signs in to their own account.',
        'teacher_busy' => 'This trainer has running courses or sessions ahead. Move them to another trainer first (from each trainee\'s account, or from trainers\' time off).',
        'student_busy' => 'This trainee has running courses. End them first from their account.',
    ],
];
