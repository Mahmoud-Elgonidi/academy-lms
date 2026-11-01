<?php

// Quran certificates (Actions\Certificates\*, the certificate's page).
return [
    'title' => 'Certificate of Quran memorization',
    'attributes' => ['reason' => 'reason'],
    'errors' => [
        'off' => 'The academy does not give Quran certificates now (Quran settings).',
        'program' => 'This program does not lead to a certificate.',
        'not_passed' => 'A certificate is only for a passed exam.',
        'revoked' => 'This certificate is already revoked.',
    ],
    'page' => [
        'print' => 'Print or save as PDF',
        'certifies' => 'This certifies that',
        'completed' => 'has recited before the examiner and completed',
        'program' => 'Program',
        'riwaya' => 'Riwaya',
        'grade' => 'Grade',
        'examiner' => 'Examiner',
        'issued' => 'Issued',
        'number' => 'Number',
        'verify' => 'Verify this certificate with its code',
        'signature' => 'For :academy',
        'revoked' => 'This certificate was revoked on :day and no longer stands.',
    ],
];
