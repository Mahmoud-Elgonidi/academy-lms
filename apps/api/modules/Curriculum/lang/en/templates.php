<?php

// The ready outlines a curriculum may start from (Outline\Templates): edited in the review before anything is saved.
return [
    'juz_amma' => [
        'name' => 'Juz Amma',
        'hint' => 'The surahs of Juz Amma from An-Nas to An-Naba, a lesson for each with its text from the platform\'s copy.',
        'level' => 'Juz Amma',
        'lesson' => 'Surah :name',
        'objective' => 'The student memorises Surah :name and recites it correctly.',
    ],
    'arabic_letters' => [
        'name' => 'The Arabic letters',
        'hint' => 'Twenty-eight lessons, a letter in each.',
        'level' => 'The Arabic letters',
        'lesson' => 'The letter :name (:letter)',
        'objective' => 'The student knows the letter :name and its sound, and writes it at the start, middle and end of a word.',
        'letters' => ['Alif', 'Baa', 'Taa', 'Thaa', 'Jeem', 'Haa', 'Khaa', 'Daal', 'Dhaal', 'Raa', 'Zaay', 'Seen', 'Sheen', 'Saad', 'Daad', 'Taa (heavy)', 'Dhaa (heavy)', 'Ayn', 'Ghayn', 'Faa', 'Qaaf', 'Kaaf', 'Laam', 'Meem', 'Noon', 'Haa (light)', 'Waaw', 'Yaa'],
    ],
    'english_cefr' => [
        'name' => 'English, A1 to C1',
        'hint' => 'Five levels of four general units each, every unit with vocabulary, grammar and speaking.',
        'level' => 'Level :code',
        'lesson' => ':skill: :topic',
        'skills' => ['Vocabulary', 'Grammar', 'Speaking and listening'],
        'levels' => [
            'A1' => ['Greetings and introductions', 'Family and friends', 'Daily routine', 'Food and shopping'],
            'A2' => ['Free time and hobbies', 'Travel and places', 'Health and the body', 'Past events'],
            'B1' => ['Work and study', 'Plans and the future', 'Stories and experiences', 'Opinions and discussion'],
            'B2' => ['The media and the news', 'Science and technology', 'The environment', 'Formal writing'],
            'C1' => ['Academic English', 'Presentations', 'Debate and persuasion', 'Advanced listening and reading'],
        ],
    ],
    'noorani' => [
        'name' => 'Noorani Qaida',
        'hint' => 'The lessons of the Noorani Qaida under general names, from single letters to reading the Quran.',
        'level' => 'Noorani Qaida',
        'lessons' => [
            'Single letters',
            'Joined letters',
            'Disjointed letters',
            'The short vowels: fatha, kasra and damma',
            'Tanween',
            'Practice: vowels and tanween',
            'The small alif, yaa and waaw',
            'The long vowels',
            'The soft letters',
            'Sukoon',
            'Practice: sukoon',
            'Shadda',
            'Shadda with vowels and tanween',
            'Shadda with sukoon',
            'Hamzat al-wasl',
            'The sun and moon letters',
            'Applying it to recitation',
        ],
    ],
];
