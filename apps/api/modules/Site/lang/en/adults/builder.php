<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../builder.php.
// Its page is for adults only, so the lines about children and families read neutrally here.
return [
    'actions' => [
        'settings' => 'On/off and introductory sessions',
    ],
    'types' => [
        'subjects' => ['name' => 'Areas and programmes', 'hint' => 'Your areas, levels and programmes, straight from Areas.'],
        'teachers' => ['name' => 'Trainers', 'hint' => 'The cards of the trainers who chose to show on the page.'],
        'steps' => ['name' => 'How it starts', 'hint' => 'Numbered steps from the request to the first session.'],
        'testimonials' => ['name' => 'What trainees say', 'hint' => 'Real words from the people you trained, with their consent.'],
        'gallery' => ['name' => 'Gallery', 'hint' => 'Pictures of your sessions and place, larger on a click.'],
        'cards' => ['name' => 'Cards', 'hint' => 'Your own programmes or topics: a picture, a title, a short text, a label and a button each.'],
        'form' => ['name' => 'Introductory session form', 'hint' => 'The form to ask for a free introductory session, and "teach with us" if you like.'],
    ],
    'fields' => [
        'trust_hint' => 'Short ticked lines, such as "A free introductory session". Leave empty for the page\'s own words for its audience.',
        'ask_label' => 'Text of "ask for a session in this area"',
        'show_badges' => 'Introductory sessions and groups',
        'per_lesson' => 'Show the price per session',
        'who_hint' => 'For the person asking only: they give their own name, with no “Who is the session for?” question. For someone else only: the person asking gives their name, then the trainee’s name and age.',
    ],
    'options' => [
        'action' => ['trial' => 'The introductory session form'],
        'who' => ['both' => 'The person asking or someone else (the form asks who the session is for)', 'adults' => 'The person asking only (the trainee books for themselves)', 'children' => 'Someone else only (booked for another person)'],
    ],
    'live' => [
        'subjects' => 'Areas, their levels and programmes are managed in Areas and Training content.',
        'teachers' => 'Each trainer writes their own card and chooses to show it. You can hide a card in "The academy\'s page".',
        'form' => 'This section is the introductory session form. Everything shown beside it is written here, and here you choose who books from it.',
        'cards' => 'Write your programmes or topics here yourself, up to 12. They do not come from Areas. A button can open the introductory session form, go to a section such as Contact, or open a link.',
    ],
    'form' => [
        'choices_hint' => 'Who asks for the introductory session, and whether the “Teach with us” form shows beside it.',
        'adults_page' => 'This page is for adults, so only trainees book for themselves here.',
        'sources' => 'The “Which area would you like training in?” list is the academy’s areas in Areas, named as you write them there. The “Help me choose” area, who books the time (the person asking or your team), the response time and the earliest bookable time are all in The academy’s page → Introductory session. The convenient days and hours and the preferred trainer are optional questions the person answers if they wish.',
        'open_subjects' => 'Open Areas',
        'open_trials' => 'Open Introductory session',
    ],
    'page' => [
        'audiences' => ['families' => 'Someone booking for another person', 'adults' => 'Adults training for themselves'],
    ],
    'templates' => [
        'online_courses' => ['name' => 'Online training academy', 'line' => 'Text and picture opening, programmes, trainers, prices, and questions in two columns.'],
    ],
    'rules' => [
        'no_form' => 'The introductory session form does not show: no requests will come from the page, and introductory session buttons will not show.',
    ],
];
