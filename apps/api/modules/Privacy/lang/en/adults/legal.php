<?php

// The privacy, terms and deletion pages of an academy that trains adults only (docs/architecture/02, section 29):
// only the keys whose words differ, list items by their place in the base list.
return [
    'privacy' => [
        'intro' => [
            'academy' => ':controller runs :academy and decides how your data is used (it is the controller). It runs its training programmes on :platform, which keeps the data for it and uses it only for that (the processor). This notice says what is kept, why, who sees it, for how long, and what you can do about it.',
        ],
        'sections' => [
            'keep' => [
                'items' => [
                    1 => 'About you as a trainee: gender and country, and your birth date if you give it.',
                    2 => 'Sessions: their times, who attended and when (from the virtual session room), cancellations, the trainer\'s reports and tasks, and what the trainee hands in (text, voice recordings, files).',
                    5 => 'Messages: the notifications sent to you, what you write to support, and your ratings of sessions.',
                    6 => 'Session recordings: only when the academy turns them on and everyone recorded agreed.',
                    8 => 'Requests from the website: when you ask an academy for a free trial session or to train there from its page, or ask for your academy to join the platform: your name and mobile number, your email and country if you give them, the fields, and your message.',
                    9 => 'A trainer\'s card on the academy\'s page (the name to show, a few words and a photo): only when the trainer turns it on, and the academy may take it off. Never a phone number or email.',
                ],
            ],
            'why' => [
                'items' => [
                    0 => 'To give the sessions you signed up for: the timetable, the session room, reports, progress and billing (the agreement with you).',
                    2 => 'Keeping accounts safe, settling disputes about sessions, and the quality of training (a legitimate interest).',
                    3 => 'Session recordings: only with consent, which can be withdrawn at any time.',
                ],
            ],
            'children' => [
                'title' => 'Your own account',
                'body' => 'Every trainee signs in and joins their sessions themselves, sees their own sessions, reports and invoices, and asks for a copy of their data or its erasure themselves.',
            ],
            'who' => [
                'items' => [
                    1 => 'The trainer: the trainee\'s name, sessions, reports and tasks. Never your phone number or email.',
                    2 => 'Others in a group session: each other\'s first name only. Never another trainee\'s phone number, email, reports or balance.',
                    3 => 'Services that run parts of the sessions, under contract and only for that: hosting and backups, email, text and WhatsApp messages, app notifications (Google and Apple), the virtual session room, and the academy\'s payment provider.',
                    4 => 'AI services, only for the helpers the academy turns on (practice questions from a text of the content, a report drafted from the trainer\'s voice note, the wording of a weekly message, a curriculum outline from what the academy describes): each gets only what that helper needs, never a name, under contract: it keeps it no longer than its service and its safety checks need, and never uses it to train its models. Where the platform runs its own AI server, the words never leave it. What they suggest is checked by a person.',
                ],
            ],
            'how_long' => [
                'items' => [
                    2 => 'Session recordings: the days the academy sets, then deleted.',
                    5 => 'After an erasure, what must stay (invoices, the session history, trainers\' pay) stays without your name.',
                ],
            ],
            'rights' => [
                'items' => [
                    3 => 'Withdrawing a consent you gave (session recordings).',
                ],
            ],
        ],
    ],

    'terms' => [
        'intro' => [
            'academy' => 'These terms are between you and :controller, which gives training through :academy. Using the app or the website means you accept them.',
        ],
        'sections' => [
            'account' => [
                'body' => 'Keep your sign-in to yourself, and tell the academy if someone else used it. The academy may suspend an account that breaks these terms.',
            ],
            'lessons' => [
                'title' => 'Sessions',
                'body' => 'Sessions are at the times agreed with the academy. Cancelling, missed sessions and make-up sessions follow the academy\'s policy, which you see before you cancel. A session\'s outcome can be disputed within 48 hours.',
            ],
            'conduct' => [
                'title' => 'In sessions',
                'body' => 'Be respectful in sessions and messages. Don\'t record sessions yourself, share a session room link, or arrange sessions with a trainer outside the academy.',
            ],
            'recordings' => [
                'body' => 'A session is recorded only when the academy turns it on and everyone recorded agreed. Recordings are kept for the days the academy sets and are watched only inside the app.',
            ],
            'content' => [
                'body' => 'The content and materials are the academy\'s, and the app is :platform\'s: use them for your own training only. What you hand in stays yours; you allow the academy to use it to train you.',
            ],
            'service' => [
                'body' => 'The service is kept running with care, but connections and devices can fail. A session lost to a problem on the academy\'s or the platform\'s side is made up under the academy\'s policy. Nothing here takes away a right the law gives you.',
            ],
            'ending' => [
                'body' => 'You can stop at any time and ask for your account to be erased. Sessions already given remain due.',
            ],
        ],
    ],

    'deletion' => [
        'intro' => [
            'academy' => 'You can ask :academy to erase your account at any time. Here is how, and what happens.',
        ],
        'sections' => [
            'how' => [
                'items' => [
                    3 => 'The academy\'s team can also write the request down for you if you contact them.',
                ],
            ],
            'then' => [
                'items' => [
                    0 => 'The academy answers within 30 days. If sessions are still booked, a course is running or an invoice is unpaid, it settles those with you first.',
                ],
            ],
        ],
    ],
];
