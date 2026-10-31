<?php

return [
    'title' => 'Recitation check',
    'toggle' => 'Check the recitation automatically',
    'hint' => 'When the student hands in a recording, the words missed or said wrong are marked for you. For you alone; the family never sees it.',
    'note' => 'A help while marking, not a mark: the words are heard automatically and can be wrong, so listen to the recording yourself.',
    'suggestion' => 'From the lesson\'s text: :title',
    'fields' => ['surah' => 'Surah', 'from' => 'From verse', 'to' => 'To verse'],
    'attributes' => ['homework.expected.surah' => 'surah', 'homework.expected.from' => 'from verse', 'homework.expected.to' => 'to verse'],
    'statuses' => [
        'pending' => 'Checking the recording…',
        'ready' => 'Checked',
        'failed' => 'Could not be checked',
    ],
    'states' => ['ok' => 'Right', 'missing' => 'Missed', 'wrong' => 'Wrong', 'extra' => 'Extra'],
    'summary' => ':ok of :words right · :missing missed · :wrong wrong · :extra extra',
    'heard' => 'What was heard',
    'instead' => 'Said: :word',
    'verse' => 'Verse :number',
    'errors' => [
        'off' => 'The recitation check is not available in this academy.',
        'not_audio' => 'The check works on homework handed in by voice.',
        'verses' => 'Those verses are not in the surah.',
        'too_long' => 'That passage is too long to check automatically; split it over more than one homework.',
    ],
];
