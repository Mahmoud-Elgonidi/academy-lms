<?php

return [
    'kinds' => ['wrong' => 'Wrong', 'hesitation' => 'Hesitation', 'tajweed' => 'Tajweed'],
    'attributes' => ['surah' => 'surah', 'verse' => 'verse', 'word' => 'word', 'kind' => 'kind of mistake', 'page' => 'page'],
    'errors' => [
        'teacher' => "Only the lesson's teacher turns the pages and marks words.",
        'closed' => "Words can be marked until the lesson's report is handed in, or a day after it ends.",
        'word' => 'That word is not in the verse.',
        'page' => 'That page is not in the mushaf.',
    ],
    'title' => 'Mushaf',
    'book' => 'Mistakes notebook',
    'book_empty' => 'No words marked in the last six months.',
    'book_words' => '{1} One word|[2,*] :count words',
    'times' => '{1} once|{2} twice|[3,*] :count times',
    'last' => 'last :when',
    'kids' => [
        'book' => 'Words to practise',
        'book_empty' => 'No words to practise right now. Well done!',
    ],
    'from_mistakes' => "From the lesson's mistakes: :title",
];
