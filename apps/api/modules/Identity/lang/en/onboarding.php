<?php

return [
    'nav' => 'New academy',
    'title' => 'New academy',
    'subtitle' => 'The academy, its address, and its owner. The owner gets a link to choose their password.',
    'sections' => [
        'academy' => 'Academy',
        'owner' => 'Owner',
    ],
    'fields' => [
        'name_ar' => 'Arabic name',
        'name_en' => 'English name',
        'slug' => 'Address',
        'slug_hint' => 'Lowercase letters, digits and -, 3 to 40 characters.',
        'currency' => 'Base currency',
        'timezone' => 'Time zone',
        'locale' => 'Language',
        'country' => 'Country',
        'country_hint' => 'Two-letter code, such as SA or EG.',
        'owner_name' => 'Owner\'s name',
        'owner_email' => 'Owner\'s email',
        'owner_phone' => 'Owner\'s mobile (optional)',
    ],
    'locales' => ['ar' => 'Arabic', 'en' => 'English'],
    'create' => 'Create academy',
    'done' => [
        'title' => ':name is ready',
        'body' => 'Send the owner this link: they choose their password once and land in the academy\'s back office. It works for :days days.',
        'copy' => 'Copy link',
        'copied' => 'Copied',
        'open' => 'Open the academy\'s page',
        'another' => 'Another academy',
    ],
    'errors' => [
        'owner_email' => 'Write a valid email for the owner.',
        'owner_phone' => 'Write the mobile with its country code, such as +966501234567.',
    ],
];
