<?php

/*
 * Livewire's settings that differ from its defaults (the rest stay as
 * vendor/livewire/livewire/config/livewire.php has them). The academy's
 * page builder uploads videos up to 50 MB (docs/architecture/site-builder.md,
 * 2.3); each upload box still sets its own, smaller limit, and the Action
 * that keeps a file checks it again.
 */
return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:51200'],      // 50 MB (Livewire's default is 12 MB)
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 10,                            // minutes: a large video on a slow line
        'cleanup' => true,
    ],
];
