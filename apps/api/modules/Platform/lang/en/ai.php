<?php

// Settings › AI: the academy's helpers, what each does, and where its data goes.
return [
    'providers' => [
        'claude' => 'Claude (Anthropic)',
        'openai' => 'GPT (OpenAI)',
        'gemini' => 'Gemini (Google)',
        'ollama' => 'the platform\'s own AI server',
    ],
    'settings' => [
        'nav' => 'AI',
        'title' => 'AI helpers',
        'subtitle' => 'Helpers that write drafts and save time. Each is off until you turn it on.',
        'promise' => 'A helper writes a draft or a note; someone at the academy reads it and decides. No grade or message leaves on its own, and what is sent is processed under contract and never used to train any model.',
        'on' => 'On',
        'not_configured' => 'This helper is not set up on the platform yet: the platform team must add its key first.',
        'saved' => 'Saved.',
        'usage' => 'Used today: :today of :cap.',
        'helpers' => [
            'practice_items' => [
                'what' => 'In the curriculum builder: a button that suggests practice questions for a lesson (choice, order, pairs) for you to review before saving.',
                'data' => 'Sent to :provider: the subject, the lesson, its objectives and any text you add. No one\'s name.',
            ],
            'report_draft' => [
                'what' => 'After a lesson the teacher records a short voice note and the helper fills in the report as a draft, which the teacher checks and sends.',
                'data' => 'The teacher\'s voice goes to ElevenLabs to become text, and the text with the report\'s choices to :provider. The recording is deleted at once, never kept.',
            ],
            'recitation_check' => [
                'what' => 'When a student hands in a recitation, the helper compares it with the expected text and marks missing or wrong words, for the teacher only.',
                'data' => 'The student\'s recording goes to ElevenLabs to become text; the comparison is done here. The family never sees the result, and the teacher grades.',
            ],
            'digest_wording' => [
                'what' => 'The weekly note to guardians is written warmly from the week\'s facts, instead of the fixed list.',
                'data' => 'Sent to :provider: the week\'s numbers, lesson titles and the teacher\'s notes for the student, with the child written as "the child", no name. No internal notes.',
            ],
        ],
    ],
    // What the demo driver "hears" (AI_DRIVER=demo, never in production).
    'demo' => [
        'heard' => [
            'practice_items' => 'A demo lesson',
            'report_draft' => 'We finished An-Nasr and he knew it well, we reviewed Al-Kafirun and he needed focus. Homework: recite An-Nasr three times. Great engagement.',
            'recitation_check' => 'بسم الله الرحمن الرحيم إذا جاء نصر الله والفتح ورأيت الناس يدخلون في دين الله أفواجا فسبح بحمد ربك واستغفره إنه كان توابا',
            'digest_wording' => 'A demo week',
        ],
    ],
];
