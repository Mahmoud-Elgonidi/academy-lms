<?php

// The academy's Quran settings (SaveQuranSettings).
return [
    'riwayat' => ['hafs' => "Hafs from 'Asim"],
    'mushafs' => ['madinah_604' => 'The Madinah mushaf (604 pages)'],
    'examiners' => ['other_teacher' => 'Another teacher', 'supervisor' => 'A supervisor', 'any_teacher' => 'Any teacher, their own too'],
    'attributes' => [
        'hifz_daily_pages' => 'pages in the daily review', 'riwaya' => 'riwaya', 'mushaf' => 'mushaf', 'default_unit' => 'default unit',
        'default_new_amount' => 'default new amount', 'default_near_portions' => 'default near review', 'default_far_pages' => 'default far review',
        'mistake_types' => 'mistake types', 'grade_scale' => 'grade scale', 'memorization_threshold' => 'memorization threshold',
        'auto_recitation_check' => 'automatic recitation check', 'exams_enabled' => 'exams', 'examiner' => 'examiner',
        'certificates_enabled' => 'certificates', 'certificate_title' => 'certificate title', 'certificate_signature' => 'certificate signature',
        'certificates_family' => 'certificates shown to families',
    ],
    'errors' => [
        'types' => 'Up to 12 kinds of mistake.',
        'type_key' => 'Each kind of mistake needs its own key: lowercase letters, digits and _.',
        'type_weight' => 'A mistake weighs 0 to 5.',
        'type_name' => 'Give each new kind of mistake a name in Arabic and in English, up to 40 characters.',
        'type_built_in' => 'Wrong, hesitation and tajweed always stay (their weights may change).',
        'scale' => 'Each grade ends above the one before it, at most 50.',
    ],
];
