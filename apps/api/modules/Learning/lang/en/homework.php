<?php

return [
    'statuses' => ['assigned' => 'To do', 'submitted' => 'Handed in', 'reviewed' => 'Marked', 'missed' => 'Missed'],
    'accepts' => ['any' => 'Any way', 'text' => 'Text', 'audio' => 'Voice recording', 'file' => 'File or photo'],
    'attributes' => ['body' => 'answer', 'file' => 'file', 'grade' => 'grade', 'feedback' => 'teacher feedback'],
    'errors' => [
        'closed' => 'This homework has already been marked.',
        'not_submitted' => 'This homework has not been handed in yet.',
        'empty' => 'Write an answer or attach a file.',
    ],
];
