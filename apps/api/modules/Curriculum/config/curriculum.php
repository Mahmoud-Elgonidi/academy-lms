<?php

return [
    // The Quran's text: where the server fetches Tanzil's files (curriculum:quran --fetch, the deploy, the console's
    // Platform settings). The Simple text "with aya numbers" (surah|verse|text) with its pause marks, and its metadata.
    'quran' => [
        'text_url' => env('QURAN_TEXT_URL', 'https://tanzil.net/pub/download/index.php?quranType=simple&outType=txt-2&marks=true&agree=true'),
        'data_url' => env('QURAN_DATA_URL', 'https://tanzil.net/res/text/metadata/quran-data.xml'),
        'timeout' => 120,
    ],
];
