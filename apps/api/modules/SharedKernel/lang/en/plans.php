<?php

// Plan limits and areas (SharedKernel\Plans), in every module's words.
return [
    'features' => [
        'crm' => 'Leads and trial lessons',
        'online_payments' => 'Online payments',
        'import' => 'Data import',
        'custom_branding' => 'Your own colour templates',
        'payroll' => "Teachers' pay",
        'ai' => 'AI helpers',
    ],
    'limits' => [
        'teachers' => 'Teachers',
        'active_students' => 'Active students',
        'video_minutes' => 'Video minutes a month',
        'storage_mb' => 'File storage',
    ],
    'reached' => [
        'teachers' => "The academy's plan allows :max teachers. Ask the platform team to raise it.",
        'active_students' => "The academy's plan allows :max active students. Ask the platform team to raise it.",
        'video_minutes' => "This month's video minutes are used up (:max minutes).",
        'storage_mb' => 'The file storage is full (:max MB).',
    ],
    'not_in_plan' => ":feature is not in the academy's plan. Ask the platform team to add it.",
];
