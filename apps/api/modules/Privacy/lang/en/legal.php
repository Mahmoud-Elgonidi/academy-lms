<?php

// The public privacy notice, terms and account-deletion page (/privacy, /terms, /account-deletion). If a change
// alters their meaning, raise LegalController::VERSION (the date at the top of each page).
return [
    'updated' => 'Last updated :date',
    'languages' => ['ar' => 'العربية', 'en' => 'English'],
    'from_academy' => 'From :academy',
    'contact_in_app' => 'the academy, through Help in the app or on the website',
    'nav' => ['privacy' => 'Privacy', 'terms' => 'Terms', 'deletion' => 'Delete your account'],

    'privacy' => [
        'title' => 'Privacy notice',
        'intro' => [
            'academy' => ':controller runs :academy and decides how your data is used (it is the controller). It runs its lessons on :platform, which keeps the data for it and uses it only for that (the processor). This notice says what is kept, why, who sees it, for how long, and what you can do about it.',
            'platform' => ':platform runs online academies that teach one to one. Each academy decides how its students\', guardians\' and teachers\' data is used (it is the controller); :platform keeps the data for it and uses it only to run the academy\'s lessons (the processor). This notice covers the app and the website; your academy\'s own page, on its address, names it and how to reach it.',
        ],
        'sections' => [
            'keep' => [
                'title' => 'What is kept',
                'items' => [
                    'Your account: name, email, phone, username, language, time zone, and how you sign in (password, one-time codes, Google or Apple). A password is kept only as a hash.',
                    'For a student: birth date, gender, country and school year, and the guardians who follow them.',
                    'Lessons: their times, who attended and when (from the video classroom), cancellations, the teacher\'s reports and homework, and what the student hands in (text, voice recordings, files).',
                    'Payments: invoices, payments and the receipts you send. Card details go straight to the payment provider; they are never seen or kept here.',
                    'Messages: the notifications sent to you, what you write to support, and your ratings of lessons.',
                    'Lesson recordings: only when the academy turns them on and everyone recorded agreed (a guardian for a child).',
                    'Technical: the devices you sign in on, their notification tokens, and a security log of sign-ins and sensitive steps.',
                ],
            ],
            'why' => [
                'title' => 'Why',
                'items' => [
                    'To give the lessons you signed up for: the timetable, the classroom, reports, progress and billing (the agreement with you).',
                    'Tax invoices and accounts the law asks for (a legal obligation).',
                    'Keeping accounts safe, settling disputes about lessons, and the quality of teaching (a legitimate interest).',
                    'A child\'s own account and lesson recordings: only with consent, which can be withdrawn at any time.',
                    'Data is never sold, used for advertising, or used to train AI models.',
                ],
            ],
            'children' => [
                'title' => 'Children',
                'body' => 'A student under the age the academy sets (18, unless it chose 16 or 13) signs in and takes their own seat in a lesson only after a guardian agrees. Guardians see their children\'s lessons, reports and invoices, and can ask for a copy of a child\'s data or its erasure. Younger students never see prices or payments.',
            ],
            'who' => [
                'title' => 'Who sees it',
                'items' => [
                    'The academy\'s staff, each only what their work needs.',
                    'The teacher: the student\'s name, lessons, reports and homework. Never a family\'s phone number or email.',
                    'Services that run parts of the lessons, under contract and only for that: hosting and backups, email, text and WhatsApp messages, app notifications (Google and Apple), the video classroom, and the academy\'s payment provider.',
                    'AI services, only for the helpers the academy turns on (practice questions from a lesson\'s text, a report drafted from the teacher\'s voice note, checking a recited passage, the wording of a weekly message to guardians): each gets only what that helper needs, under contract, and may not keep it or use it to train its models. What they suggest is checked by a person.',
                    'Authorities, only where the law requires it.',
                ],
            ],
            'where' => [
                'title' => 'Where it is kept',
                'body' => 'On :platform\'s servers, encrypted on its way to you, with encrypted backups kept away from the server. Some of the services above may handle data in other countries; they do so under contracts that protect it as the law requires.',
            ],
            'how_long' => [
                'title' => 'For how long',
                'items' => [
                    'While the account is in use.',
                    'Invoices and payments: as long as tax law requires (six years in Saudi Arabia).',
                    'Lesson recordings: the days the academy sets, then deleted.',
                    'A copy of your data: seven days after it is ready.',
                    'After an erasure, what must stay (invoices, the lesson history, teachers\' pay) stays without your name.',
                ],
            ],
            'rights' => [
                'title' => 'Your rights',
                'items' => [
                    'A copy of your data, in a file you can take elsewhere.',
                    'Correcting it: change what you can in your settings, or ask the academy.',
                    'Erasing your account (see "Delete your account").',
                    'Withdrawing a consent you gave (a child\'s account, recordings).',
                    'Objecting to a use, or complaining to the data protection authority: in Saudi Arabia the Saudi Data and AI Authority (SDAIA), in Egypt the Personal Data Protection Center, in the European Union your country\'s authority.',
                ],
            ],
            'ask' => [
                'title' => 'How to ask',
                'body' => 'In the app or on the website: "My data", in your account. Or write to :contact. The academy answers within 30 days.',
            ],
            'security' => [
                'title' => 'Security',
                'body' => 'Connections are encrypted; the people who handle money and data sign in in two steps; every sensitive step is recorded; each academy\'s data is kept apart from every other\'s. If a breach puts your data at risk, the authority and you are told as the law requires.',
            ],
            'changes' => [
                'title' => 'Changes',
                'body' => 'When this notice changes in meaning, the date at the top changes and you are told in the app.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of use',
        'intro' => [
            'academy' => 'These terms are between you and :controller, which gives lessons through :academy. Using the app or the website means you accept them.',
            'platform' => ':platform provides the app and the website academies give their lessons through. Your agreement about lessons and payments is with your academy; these terms cover using the app and the website.',
        ],
        'sections' => [
            'account' => [
                'title' => 'Your account',
                'body' => 'Keep your sign-in to yourself, and tell the academy if someone else used it. A guardian is responsible for a child\'s account. The academy may suspend an account that breaks these terms.',
            ],
            'lessons' => [
                'title' => 'Lessons',
                'body' => 'Lessons are at the times agreed with the academy. Cancelling, missed lessons and make-up lessons follow the academy\'s policy, which you see before you cancel. A lesson\'s outcome can be disputed within 48 hours.',
            ],
            'payments' => [
                'title' => 'Payments',
                'body' => 'Prices, packages and renewals are the academy\'s and are on each invoice. You pay the academy itself, online or by transfer with a receipt. Refunds follow the academy\'s policy and the law.',
            ],
            'conduct' => [
                'title' => 'In lessons',
                'body' => 'Be respectful in lessons and messages. Don\'t record lessons yourself, share a class link, or arrange lessons with a teacher outside the academy.',
            ],
            'recordings' => [
                'title' => 'Recordings',
                'body' => 'A lesson is recorded only when the academy turns it on and everyone recorded agreed. Recordings are kept for the days the academy sets and are watched only inside the app.',
            ],
            'content' => [
                'title' => 'Materials',
                'body' => 'The curriculum and materials are the academy\'s, and the app is :platform\'s: use them for your own lessons only. What you hand in stays yours; you allow the academy to use it to teach you.',
            ],
            'service' => [
                'title' => 'The service',
                'body' => 'The service is kept running with care, but connections and devices can fail. A lesson lost to a problem on the academy\'s or the platform\'s side is made up under the academy\'s policy. Nothing here takes away a right the law gives you.',
            ],
            'ending' => [
                'title' => 'Stopping',
                'body' => 'You can stop at any time and ask for your account to be erased. Lessons already given remain due.',
            ],
            'law' => [
                'title' => 'Law and contact',
                'body' => 'These terms follow the law of the academy\'s country. Write to :contact first; most problems are settled there.',
            ],
        ],
    ],

    'deletion' => [
        'title' => 'Delete your account',
        'intro' => [
            'academy' => 'You can ask :academy to erase your account, or your child\'s, at any time. Here is how, and what happens.',
            'platform' => 'You can ask your academy to erase your account, or your child\'s, at any time. Here is how, and what happens.',
        ],
        'sections' => [
            'how' => [
                'title' => 'How to ask',
                'items' => [
                    'In the app: Account, then "My data", then "Delete my account".',
                    'On the website: sign in, then "My data", then "Delete my account".',
                    'Or write to :contact from the email or phone number on your account.',
                    'A guardian asks for a child\'s account on the same page, choosing the child.',
                ],
            ],
            'then' => [
                'title' => 'What happens next',
                'items' => [
                    'The academy answers within 30 days. If lessons are still booked, a course is running or an invoice is unpaid, it settles those with you first.',
                    'Once approved, the account is erased a day later; until then you can take the request back.',
                    'You are signed out everywhere and can no longer sign in.',
                ],
            ],
            'goes' => ['title' => 'What is erased'],
            'stays' => ['title' => 'What is kept, without your name'],
            'copy' => [
                'title' => 'Before you go',
                'body' => 'You can download a copy of your data from "My data" first. It is ready within minutes and kept for seven days.',
            ],
        ],
    ],
];
