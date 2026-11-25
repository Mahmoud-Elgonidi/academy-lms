<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'chest' => [
        'not_ready' => 'The chest opens once every session of the week is attended and every task handed in.',
        'hint' => 'Attend every session this week and hand in all its tasks, and the chest opens.',
    ],
    'notice' => ['marked' => 'You attended the session'],
    'missions' => ['lesson' => 'Session', 'homework' => 'Task'],
    'styles' => ['playful' => 'Playful: with islands and games'],
    'streak' => '{0} Start a new attendance streak|{1} You attended the last session|[2,*] :count sessions in a row',
    'courses' => 'Your programmes',
    'no_program' => 'The trainer is still preparing this programme. Your missions are below.',
    'none' => 'No programmes yet.',
    'finished' => 'You finished the whole programme!',
    'island' => ['homework' => 'Task'],
    'station' => [
        'locked' => 'You have not reached this lesson yet. You can practise it once the trainer has covered it.',
        'chosen' => 'Trainer\'s pick',
    ],
    'mission' => [
        'join' => 'Join the session',
        'opens' => 'The session room opens :time',
    ],
    'last_lesson' => [
        'title' => 'Last session',
        'got' => 'You got :stars in your last session',
    ],
    'calm' => [
        'course' => ':pct% of the programme',
        'finish_on' => 'At this pace you will finish around :date',
        'finish_unknown' => 'The finish date shows after a few more sessions.',
        'next_lesson' => 'Next session',
        'no_lesson' => 'No sessions in the next two weeks.',
        'roadmap' => 'The programme in detail',
        'reports' => 'Trainer\'s reports',
    ],
    'settings' => [
        'hint' => 'How trainees see their journey through the programme: islands and games, calm levels and missions, or no stars at all (where an adults academy starts).',
    ],
];
