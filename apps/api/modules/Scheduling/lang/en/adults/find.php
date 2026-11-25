<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'nav' => 'Find a trainer',
    'title' => 'Find a trainer',
    'fields' => [
        'student' => 'Trainee',
        'timezone' => 'Trainee timezone',
        'subject' => 'Area',
        'language' => 'Training language',
        'gender' => 'Trainer gender',
        'age_group' => "Trainee's age group",
    ],
    'any_age' => 'Any age group',
    'modes' => ['series' => 'Weekly sessions', 'trial' => 'Introductory session'],
    'genders_short' => ['female' => 'Female trainer', 'male' => 'Male trainer'],
    'grid' => [
        'title' => 'Times that suit the trainee',
    ],
    'steps' => [
        'who' => 'The trainee and the area',
        'who_hint' => 'The trainee brings their timezone, age group and preferred trainer; the rest is under "More options".',
        'teachers' => 'The trainers',
    ],
    'tabs' => ['mine' => 'Times that suit the trainee'],
    'more' => [
        'hint' => 'Language, level, category, age group, trainer gender and availability',
    ],
    'free' => [
        'wait' => [
            'title' => 'Choose the area first',
            'hint' => 'The trainers\' free times and suggested times appear here once the area is chosen.',
        ],
        'suggestions_hint' => '{1} One session a week, where the most trainers are free. Choose one to see them.|[2,*] :n sessions a week on different days, where the most trainers are free. Choose one to see them.',
        'no_suggestions' => 'No set of times on enough different days: choose hours on the map, or fewer sessions a week.',
        'teachers' => '{1} one trainer free|[2,*] :count trainers free',
        'map' => 'Free trainers across the week',
        'map_hint' => 'Each square counts the trainers free for a :minutes-minute session in that hour. Hover for their names.',
        'tap' => 'Choose squares to add those hours to the trainee\'s times, then search.',
    ],
    'exact' => [
        'hint_series' => 'Choose the session day or days and the time to the minute: the trainers free at exactly that time every week appear.',
        'hint_trial' => 'Choose the introductory session day and time to the minute: the trainers free at exactly that time appear. "Available now" has suggestions.',
        'searched' => 'Trainers free at :at',
        'nobody' => [
            'title' => 'No trainer is free at :at',
            'hint' => 'Here is why for each trainer. Try a time nearby, or choose from "Available now".',
        ],
        'fits' => ':at is free and was added to the trainer\'s times.',
        'fits_some' => ':at is free on :n of :weeks weeks and was added to the trainer\'s times.',
        'reasons' => [
            'time_off' => 'the trainer is on leave',
            'shifts' => 'outside the trainer\'s working hours',
            'busy' => 'the trainer has a session then',
            'cap' => 'the trainer\'s day is at its session limit',
            'clock' => 'a summer time change moves the session for the trainee',
        ],
    ],
    'reasons' => [
        'no_teachers' => [
            'title' => 'No trainer in this area takes new trainees',
            'hint' => 'Add the area to a trainer\'s profile, or let one of its trainers take new trainees, from the Trainers screen.',
        ],
        'no_trial_teachers' => [
            'title' => 'No trainer in this area gives introductory sessions',
            'hint' => 'Let one of the area\'s trainers give trials from their profile on the Trainers screen.',
        ],
        'filters' => [
            'title' => 'The filters left every trainer out',
            'hint' => 'Trainers in this area are free, but none matches the language, level, category, age group or gender chosen.',
        ],
        'no_time' => [
            'title' => 'No trainer has a free time for this session',
            'hint' => 'Every free time of the area\'s trainers is taken for these weeks. Allow one week missing or try another length.',
        ],
        'times' => [
            'title' => 'No trainer is free at the hours chosen',
        ],
    ],
    'bar' => [
        'empty' => 'Choose the area, then the times.',
    ],
    'start_trial' => [
        'title' => 'Choose the area and the times that suit the trial',
        'hint' => 'The nearest free times in the next two weeks, with trainers who give trials, ranked by fit and by how many of their trial trainees went on to subscribe.',
    ],
    'start' => [
        'title' => 'Pick the area and the trainee\'s times',
        'hint' => 'Trainers are ranked by fit; times show on the trainee\'s clock with the trainer\'s underneath.',
    ],
    'none' => [
        'title' => 'No trainer is free at these times',
    ],
    'found' => '{1} one matching trainer|[2,*] :count matching trainers',
    'found_hint' => 'Ranked by fit · times in :city time, trainer\'s time underneath',
    'confirm_series' => 'Enrol the trainee (:count sessions)',
    'booked' => [
        'trial_told' => 'The trainee gets the time and a link to join without an account, and so does the trainer.',
        'joined' => 'Enrolled: :count sessions, the first on :date, from the trainee\'s shared package (no new invoice).',
        'skipped' => 'Skipped :dates: the trainer or the trainee is already booked.',
    ],
    'enroll' => [
        'joins_package' => 'The trainee already has this shared package: this programme draws on the same balance, with no new invoice. If the package is paid, the programme starts at once.',
        'title' => 'Enrol the trainee',
        'create_plan_hint' => 'A plan with the session length and sessions a week you picked. You can change its name and prices later in "Plans and prices".',
        'plan_block' => 'Sessions in one block',
        'payer_student' => ':name (the trainee)',
        'payer_guardian' => ':name (payer)',
        'submit' => 'Enrol and issue the invoice',
        'open_account' => 'Trainee account',
    ],
    'errors' => [
        'coupon_on_package' => 'This programme joins the trainee\'s shared package and has no invoice of its own. Put the coupon on the package from the trainee\'s account.',
        'no_times' => 'Mark the trainee\'s times on the grid first.',
        'student_needed' => 'Choose the trainee first.',
        'cannot_book' => 'Enrolling a trainee on a plan is for schedulers.',
        'slot_taken' => 'That weekly time was just given to another trainee of this trainer. Search again.',
    ],
];
