<?php

// The page builder's templates (Domain\Page\Templates): no academy's name inside, the page adds it.
return [
    'online_courses' => [
        'hero' => [
            'kicker' => 'Live online courses',
            'title' => 'Learn a new skill with a teacher beside you at every step',
            'lead' => 'Practical courses in live lessons, a teacher who follows your progress, and times that fit your day. Start with a free trial lesson to find your level and your plan.',
            'trust' => ['A free trial lesson', 'Flexible times on your clock', 'A report after every lesson'],
        ],
        'stats' => ['items' => [
            ['value' => '1:1', 'label' => 'One teacher for each learner'],
            ['value' => '100%', 'label' => 'Live online lessons'],
            ['value' => '0', 'label' => 'Fees for the trial lesson'],
        ]],
        'subjects' => ['title' => 'Our courses', 'lead' => 'Choose the course that fits your goal, and start at the level that suits you.', 'nav' => 'Courses'],
        'features' => [
            'title' => 'Why learners choose us',
            'nav' => 'Why us',
            'items' => [
                ['title' => 'Learn from anywhere', 'text' => 'Live lessons with sound and video, from a computer or a phone.'],
                ['title' => 'Experienced teachers', 'text' => 'We choose our teachers with care and keep an eye on every lesson.'],
                ['title' => 'Your time, your call', 'text' => 'Pick the days and hours that suit you, and change them when you need to.'],
                ['title' => 'A report after every lesson', 'text' => 'See what you did, what comes next, and how your level is moving.'],
                ['title' => 'A plan made for you', 'text' => 'We start where you are and build the plan around your goal.'],
                ['title' => 'Help when you need it', 'text' => 'Our team answers your questions before you join and after.'],
            ],
        ],
        'teachers' => ['title' => 'Meet our teachers', 'nav' => 'Teachers'],
        'pricing' => ['nav' => 'Prices'],
        'faq' => [
            'nav' => 'Questions',
            'items' => [
                ['q' => 'Is the trial lesson really free?', 'a' => 'Yes. A full lesson with a teacher, no payment and no commitment; then we suggest the plan that fits you.'],
                ['q' => 'Do I need any experience?', 'a' => 'No. We find your level in the first lesson and start from there.'],
                ['q' => 'How do lessons take place?', 'a' => 'Online with sound and video, from a computer or a phone, with nothing complicated to install.'],
                ['q' => 'Can I move a lesson?', 'a' => 'Yes. Tell us early enough and we will find another time that suits you.'],
                ['q' => 'How do I pay?', 'a' => 'By package: a number of lessons at a clear price in your currency, paid online or by transfer.'],
            ],
        ],
        'cta' => [
            'title' => 'Start today with a free lesson',
            'lead' => 'Book your first lesson, meet your teacher, and leave with a clear plan for your goal.',
            'button' => 'Book your free lesson',
        ],
    ],
    'training' => [
        'hero' => [
            'kicker' => 'Training and professional development',
            'title' => 'Training programmes that build your team\'s skills and show in its work',
            'lead' => 'Live training by specialists, a plan built on what you need, and follow-up that measures the result after every stage.',
        ],
        'logos' => ['title' => 'Organisations that trained with us'],
        'features' => [
            'title' => 'What sets our programmes apart',
            'lead' => 'We design each programme around a clear goal, and measure what it achieved.',
            'nav' => 'Programmes',
            'items' => [
                ['title' => 'Needs first', 'text' => 'We start by understanding what your team needs before we suggest anything.'],
                ['title' => 'Specialist trainers', 'text' => 'Every programme is led by a trainer with real experience in the field.'],
                ['title' => 'Hands-on practice', 'text' => 'Exercises and cases from real work, not lectures alone.'],
                ['title' => 'Regular reports', 'text' => 'A report after each stage on what was done and what needs more work.'],
            ],
        ],
        'image_text' => [
            'title' => 'Training that fits your team\'s time',
            'body' => 'Short, focused live sessions at the times you choose, for individuals or small groups, with material that stays available after each session.',
            'bullets' => ['One-to-one or small-group sessions', 'Flexible times on your team\'s clock', 'A certificate for every programme'],
        ],
        'steps' => [
            'title' => 'How we work with you',
            'nav' => 'Approach',
            'items' => [
                ['title' => 'A first meeting', 'text' => 'We learn your goals and where your team stands today.'],
                ['title' => 'A proposed plan', 'text' => 'We send you the programme, its length and its cost.'],
                ['title' => 'The training', 'text' => 'Live sessions, with follow-up after each one.'],
                ['title' => 'Measuring the result', 'text' => 'A final report on what was achieved, and what to do next.'],
            ],
        ],
        'stats' => ['items' => [
            ['value' => '1:1', 'label' => 'One-to-one sessions available'],
            ['value' => '100%', 'label' => 'Live online training'],
            ['value' => '0', 'label' => 'Fees for the first meeting'],
        ]],
        'cta' => [
            'title' => 'Let\'s design your team\'s programme together',
            'lead' => 'Book a free first meeting, and we will send you a proposed plan within days.',
            'button' => 'Book a first meeting',
        ],
        'form' => ['title' => 'Ask for a free first meeting'],
    ],
    'language_school' => [
        'hero' => [
            'kicker' => 'Languages, live online',
            'title' => 'Speak a new language with confidence',
            'lead' => 'Live conversation lessons with skilled teachers, and a course that starts at your level and takes you to your goal.',
        ],
        'subjects' => ['title' => 'The languages we teach', 'nav' => 'Languages'],
        'features' => [
            'title' => 'How we teach',
            'nav' => 'Our method',
            'items' => [
                ['title' => 'Speaking first', 'text' => 'You speak from the first lesson, and grammar comes in context.'],
                ['title' => 'Everyday language', 'text' => 'Real situations from travel, work and study.'],
                ['title' => 'A graded course', 'text' => 'Clear levels, so you always know where you are and where you are going.'],
                ['title' => 'Flexible times', 'text' => 'Choose the time that suits you, from morning to evening.'],
                ['title' => 'One-to-one or groups', 'text' => 'Choose a teacher of your own or a small group.'],
                ['title' => 'A certificate for each level', 'text' => 'You receive a certificate each time you complete a level.'],
            ],
        ],
        'teachers' => ['title' => 'Our teachers', 'nav' => 'Teachers'],
        'gallery' => ['title' => 'From our lessons'],
        'pricing' => ['nav' => 'Prices'],
        'faq' => ['items' => [
            ['q' => 'How do I know my level?', 'a' => 'We find your level in the free trial lesson and suggest where to start.'],
            ['q' => 'Are the teachers native speakers?', 'a' => 'We choose teachers who master the language and how to teach it, and you may ask for what suits you.'],
            ['q' => 'How many lessons a week do I need?', 'a' => 'Two or three a week for steady progress; choose what fits your time.'],
            ['q' => 'Is there practice between lessons?', 'a' => 'Yes. The teacher sends short exercises after each lesson to fix what you learned.'],
        ]],
    ],
    'blank' => [
        'hero' => ['title' => 'Welcome'],
    ],
];
