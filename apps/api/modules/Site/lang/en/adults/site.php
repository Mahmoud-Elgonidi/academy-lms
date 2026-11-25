<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../site.php.
// The page speaks to adults who train for themselves; request.fields says what request.fields_adults says.
return [
    'defaults' => [
        'hero' => [
            'title' => 'One-to-one online training with trainers you can trust',
            'lead' => 'One trainer for one trainee, at times that suit you, with a report on your phone after every session. Start with a free introductory session.',
        ],
        'why' => [
            'families' => ['title' => 'Training that fits you', 'lead' => 'A live session with one trainer who knows you well, and a plan that moves at your level and pace.'],
            'adults' => ['title' => 'Training that fits you', 'lead' => 'A live session with one trainer who knows you well, and a plan that moves at your level and pace.'],
        ],
        'features' => [
            ['title' => 'Live one-to-one sessions', 'text' => 'The trainer is all yours for the whole session, at your level and your pace.'],
            ['title' => 'Carefully chosen trainers', 'text' => 'We choose our trainers with care and follow their sessions; if you would like another trainer, we change them.'],
            ['title' => 'Times that suit you', 'text' => 'Pick the days and hours that suit you, on your own clock; a session you miss is made up.'],
            ['title' => 'A report after every session', 'text' => 'See what you learnt, your next task, and how your level is moving.'],
            ['title' => 'A clear training path', 'text' => 'Goals and practical tasks on what you learn, so you see your progress from one session to the next.'],
            ['title' => 'An app on your phone', 'text' => 'Times, sessions, tasks and invoices in one place on your phone.'],
        ],
        'faqs' => [
            ['q' => 'Is the introductory session really free?', 'a' => 'Yes. A full session with a trainer, with no payment and no commitment; afterwards we tell you your level and the plan that fits.'],
            ['q' => 'How do sessions take place?', 'a' => 'Online, with sound and video, from a computer or a phone. Nothing to install except our app, if you like.'],
            ['q' => 'Can I choose a male or a female trainer?', 'a' => 'Yes. Tell us in your request and we suggest who suits you.'],
            ['q' => 'What if I miss a session?', 'a' => 'Let us know early enough and the session is made up at another time that suits you.'],
            ['q' => 'How do I pay?', 'a' => 'By plan: a set number of sessions at a clear price in your currency, paid online or by transfer.'],
            ['q' => 'How will I follow my progress?', 'a' => 'You get a report after every session, and you can follow attendance, tasks and progress in the app.'],
        ],
        'seo' => [
            'title' => 'One-to-one online training with chosen trainers · free introductory session',
            'description' => 'Live one-to-one training at times that suit you, a report after every session and an app on your phone. Book a free introductory session today.',
        ],
    ],
    'sections' => [
        'subjects' => 'Areas and levels',
        'teachers' => 'Trainers',
        'testimonials' => 'What trainees say',
    ],
    'kinds' => [
        'trial' => 'introductory session',
        'teach' => 'wants to train with us',
    ],
    'settings' => [
        'subtitle' => 'What visitors see while they look for an academy: your words, areas, trainers, prices and the introductory session form. Requests arrive under "Page requests".',
        'tabs' => [
            'teachers' => 'Trainers',
            'trials' => 'Introductory session',
        ],
        'trials' => [
            'title' => 'The free introductory session',
            'hint' => 'Who books an introductory session asked for on the page, and how soon the person who asked hears back.',
            'booking' => 'Who books the introductory session',
            'booking_family_hint' => 'Whoever asked chooses from the trainers\' free times and confirms with a code sent to their number; a message brings the time and the link to join.',
            'notice_hours_hint' => 'No sooner time is offered, so the trainer can prepare.',
            'default_subject' => 'The area for "help me choose"',
            'default_subject_hint' => 'Whoever does not know which area starts with this one. Left empty, sales choose it with them.',
        ],
        'audience_hint' => 'Changes the page\'s fixed words and the introductory session form: "you" for adults training for themselves.',
        'audiences' => ['families' => 'Someone booking for another person', 'adults' => 'Adults'],
        'testimonials' => 'What trainees say',
        'sections_hint' => 'The opening and the introductory session form always show. Areas come from "Areas", prices from "Plans".',
        'teachers' => "Trainers' cards",
        'teachers_hint' => 'Each trainer writes their own card from their account ("My card on the academy\'s page") and chooses whether it shows. The academy can take any card off. No number or email is ever shown.',
        'teachers_off' => 'The published page has no trainers section, so no card shows now. Add one in "Page design".',
        'teachers_empty' => 'No trainer has made a card yet.',
        'card' => [
            'teacher_off' => 'The trainer keeps it off',
            'inactive' => 'The trainer is not active now',
        ],
    ],
    'request' => [
        'fields' => [
            'subject_id' => 'area',
            'for_child' => 'for someone else',
            'child_name' => "trainee's name",
            'child_birth_year' => "trainee's year of birth",
            'subject_ids' => 'areas you train in',
            'years_teaching' => 'years of training experience',
            'help_choose' => 'help me choose the area',
            'teacher_gender' => 'preferred trainer',
        ],
    ],
    'requests' => [
        'subtitle' => 'People who asked for an introductory session or to train with you from the academy\'s page. Introductory session requests also become leads under "Leads" by themselves.',
        'kinds' => [
            'trial' => 'Introductory sessions',
            'teach' => 'Trainers',
        ],
        'for_child' => 'For someone else: :name',
        'subject' => 'Area',
        'subjects' => 'Trains in',
        'years' => ':years years of training experience',
        'help_choose' => 'Wants help choosing the area',
        'teacher_gender' => ['female' => 'Prefers a woman trainer', 'male' => 'Prefers a man trainer'],
        'booked' => 'Booked the introductory session on their own',
    ],
    'errors' => [
        'children_only' => 'This form is for booking a session for someone else: enter their name and age and send it. If the session is for you, contact the academy.',
    ],
    'setup' => [
        'hint' => 'A page in your name with your areas, trainers and prices, where visitors ask for a free introductory session.',
    ],
];
