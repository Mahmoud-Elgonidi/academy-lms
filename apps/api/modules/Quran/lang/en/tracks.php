<?php

// The ready tracks a Quran program will start from (the Quran program, docs/architecture/02 section 26): kept here for it.
return [
    // Each track a Quran subject or program follows.
    'names' => [
        'hifz' => 'Memorization',
        'tilawa' => 'Recitation and tajweed',
        'talqin' => 'Dictation for children',
        'noorani' => 'Noorani Qaida',
        'review' => 'Review and consolidation',
        'ijaza' => 'Ijaza',
    ],
    'juz_amma' => [
        'name' => 'Juz Amma',
        'hint' => 'The surahs of Juz Amma from An-Nas to An-Naba, a lesson for each with its text from the platform\'s copy.',
        'level' => 'Juz Amma',
        'lesson' => 'Surah :name',
        'objective' => 'The student memorises Surah :name and recites it correctly.',
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
