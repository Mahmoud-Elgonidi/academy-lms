<?php

return [
    'errors' => [
        'address' => 'The address of Tanzil\'s files must start with https://.',
        'unreachable' => ':host did not answer. Check the server can reach it, then try again.',
        'status' => ':host answered with an error (:status).',
        'size' => 'What came back is not one of Tanzil\'s files (empty or far too big).',
        'data' => 'The metadata is not Tanzil\'s quran-data.xml: it must name 114 surahs and 6,236 verses.',
        'layout' => 'The metadata must place the mushaf\'s 604 pages and 30 parts, each where a verse begins, in order.',
        'line' => 'Line :line of the text is not "surah|verse|text" in Arabic: is this the Simple text "with aya numbers"?',
        'order' => 'The text skips or repeats a verse at surah :surah, verse :verse.',
        'short' => 'The text stops before the last verse.',
        'signs' => 'The text has rub-el-hizb or sajdah signs: fetch it without them.',
        'notice' => 'Tanzil\'s notice ("Tanzil Quran Text (Simple ...)") is not in the text; it is kept with every copy.',
        'write' => 'The server could not write the files to its storage.',
    ],
];
