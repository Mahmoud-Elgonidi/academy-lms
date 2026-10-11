<?php

return [
    'statuses' => ['assigned' => 'To do', 'submitted' => 'Handed in', 'reviewed' => 'Marked', 'missed' => 'Missed'],
    'accepts' => ['any' => 'Any way', 'text' => 'Text', 'audio' => 'Voice recording', 'file' => 'File or photo', 'practice' => 'Practice in the games'],
    'attributes' => ['body' => 'answer', 'file' => 'file', 'grade' => 'grade', 'feedback' => 'teacher feedback'],
    'practice_body' => '🎮 Practised in “:game”: :correct of :total right (:stars ⭐)',
    'practice' => [
        'with' => 'With practice in the games',
        'with_hint' => 'The learner practises the items of this report’s lessons in the islands’ games, and you see how it went here.',
        'none' => 'These lessons have no practice in the curriculum yet.',
        'result' => 'Practice: :correct of :total right · :stars ⭐ (:game)',
        'waiting' => 'Not practised yet.',
        'play' => 'Play the practice',
        'done' => 'Practice done',
    ],
    'errors' => [
        'closed' => 'This homework has already been marked.',
        'not_submitted' => 'This homework has not been handed in yet.',
        'empty' => 'Write an answer or attach a file.',
        'no_practice' => 'This homework has no practice in the games.',
    ],
];
