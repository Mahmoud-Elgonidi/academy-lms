<?php

return [
    'office' => [
        'nav' => 'Safeguarding',
        'title' => 'Keeping children safe',
        'lead' => 'Concerns about a child\'s safety. Only the safeguarding leads see this page: take a new one within the hour.',
        'open' => 'Open concerns',
        'none_open' => 'No open concerns.',
        'closed' => 'Closed in the last :days days',
        'waiting_hour' => 'nobody took it within the hour',
        'pick' => 'Choose a concern to read it.',
        'raised_by' => 'Raised by :who (:side) · :when',
        'urgent' => 'Marked urgent: someone may be in danger now. If so, call the emergency services first.',
        'no_words' => 'Sent without words (a child pressed the button). Reach the family gently to find out more.',
        'teacher_standing' => ':name with under-18s: :state',
        'steps' => 'What was done',
        'reply_sent' => 'Reply sent to the person who raised it',
        'private' => 'Only the safeguarding leads see this. Do not share it beyond them.',
        'actions' => [
            'record' => 'Write down a concern',
            'record_hint' => 'A concern told by phone or in person: write it down here so it is followed like the others.',
            'record_body_hint' => 'What was said, by whom and when; in their own words where you can.',
            'take' => 'Take it',
            'note' => 'Add a note',
            'classify' => 'Correct details',
            'pause' => 'Pause from under-18s',
            'pause_hint' => 'The teacher cannot be booked with anyone under 18 until you let them back; lessons with adults go on. Lessons already booked stay: change their teacher from the calendar if needed. The teacher is not told why.',
            'resume' => 'Let back to under-18s',
            'resume_hint' => 'The teacher may be booked with under-18s again (if the academy\'s check rule allows it).',
            'close' => 'Close',
            'close_hint' => 'Choose what came of it. Your note stays with the concern; the reply, if you write one, is what the person who raised it reads.',
            'close_note' => 'What was done (only the leads read this)',
            'reply_hint' => 'Optional. Keep it general: never name anyone else or say what was done about a teacher.',
            'reopen' => 'Open again',
            'closed' => 'The concern is closed.',
        ],
        'done' => [
            'recorded' => 'Concern :number written down.',
        ],
    ],

    'family' => [
        'nav' => [
            'classic' => 'Report a concern',
            'teen' => 'Report a concern',
            'middle' => 'Tell us',
            'kids' => 'Tell a grown-up',
        ],
        'title' => [
            'classic' => 'Report a concern about a child\'s safety',
            'student' => 'Something wrong? Tell us',
            'kids' => 'Is something wrong?',
        ],
        'lead' => [
            'classic' => 'If anything about a lesson, a teacher or anyone on the platform worries you, tell us here. Only the people who keep children safe at the academy read it.',
            'student' => 'If someone made you feel uncomfortable, scared or unsafe, tell us. Only the people who keep students safe at the academy read it, and you will not get in trouble.',
            'kids' => 'If someone made you sad or scared, press a button. A grown-up at the academy will help.',
        ],
        'danger' => [
            'classic' => 'If someone is in danger right now, call the emergency services first.',
            'student' => 'If you are in danger right now, tell an adult you trust or call the emergency services.',
            'kids' => 'If you are scared right now, tell a grown-up at home.',
        ],
        'about' => 'About :name',
        'what' => ['classic' => 'What is it about?', 'student' => 'What is it about?', 'kids' => 'What happened?'],
        'text' => ['classic' => 'What happened?', 'student' => 'What happened?', 'kids' => 'Do you want to say more? You don\'t have to.'],
        'text_hint' => 'When it happened, who was there, what was said: in your own words.',
        'send' => ['classic' => 'Send to the academy', 'student' => 'Send', 'kids' => 'Send'],
        'sent' => [
            'classic' => 'Thank you for telling us. The academy\'s safeguarding lead will look at it.',
            'student' => 'Thank you for telling us. You did the right thing.',
            'kids' => 'Thank you for telling us. You did the right thing.',
        ],
        'again' => 'Tell us something else',
    ],

    'teacher' => [
        'nav' => 'Raise a concern',
        'title' => 'Raise a safeguarding concern',
        'lead' => 'If something about a student\'s safety or wellbeing worries you, or another adult\'s behaviour, tell the academy\'s safeguarding leads here. Only they read it. Do not look into it or contact the family about it yourself.',
        'danger' => 'If a child is in danger right now, call the emergency services first, then write it here.',
        'student' => 'Which student?',
        'no_student' => 'Not about one student',
        'send' => 'Send to the safeguarding leads',
        'sent' => 'Thank you. The safeguarding leads have it.',
        'again' => 'Raise another',
    ],

    'form' => [
        'lesson' => 'In which lesson (if any)?',
        'no_lesson' => 'Not about a lesson',
        'urgent' => 'Someone may be in danger right now',
    ],

    'mine' => [
        'title' => 'What you told us',
        'empty' => 'Nothing yet.',
        'reply' => 'The academy\'s reply',
        'sent_on' => 'Sent :when',
        'closed_on' => 'Closed :when',
        'number' => 'No. :number',
    ],
];
