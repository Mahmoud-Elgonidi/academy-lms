<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'title' => 'Session room',
    'roles' => ['teacher' => 'Trainer', 'student' => 'Trainee'],
    'room' => [
        'leave_hint' => 'Leave the session',
        'left' => 'You left the session.',
        'panel' => 'Session panel',
    ],
    'plan' => ['none' => 'This trainee follows no programme.'],
    'materials' => [
        'title' => 'Session materials',
        'hint' => 'They open in a new tab; the session stays here.',
        'teachers_only' => 'Trainers only',
    ],
    'homework' => [
        'title' => 'Last task',
        'none' => 'No tasks yet.',
    ],
    'external' => [
        'title' => 'This session is on an outside link',
        'go' => 'Open the session',
    ],
    'refused' => [
        'title' => 'You cannot enter this session',
        'body' => 'This link has expired or is not yours. Open the session from the app, or ask the academy for a new link.',
    ],
    'video_held' => 'The academy\'s video minutes have run out, so this session is on the trainer\'s own link (Zoom or Google Meet). Press "Go to the session" to open it.',
    'video_held_go' => 'Go to the session',
    'errors' => [
        'not_yours' => 'This session is not yours.',
        'closed' => 'This session has ended or was cancelled.',
        'too_early' => 'The session room opens :before minutes before the session. :minutes minutes to go.',
        'no_link' => 'The trainer has not set their session link yet.',
        'provider' => 'The session room is not available right now. Try again shortly or contact the academy.',
        'livekit_unavailable' => 'This server has no session room of its own (LiveKit) set up yet: sessions use each trainer\'s outside link.',
    ],
    'today' => [
        'nav' => 'My sessions today',
        'title' => 'My sessions today',
        'subtitle' => 'The session room opens :minutes minutes before each session.',
        'join' => 'Join the session',
        'none' => 'No sessions today.',
        'link' => 'Fallback session link',
    ],
    'link' => [
        'hint' => 'Used when the academy runs sessions on outside links instead of its session room.',
    ],
    'settings' => [
        'nav' => 'Session room',
        'title' => 'Session room',
        'subtitle' => 'Where sessions happen. A change applies to sessions whose room has not opened yet.',
        'providers' => [
            'livekit' => 'The academy\'s session room (LiveKit): attendance is recorded by itself',
            'external' => 'An outside link per trainer (Zoom or Meet): attendance from the join click only',
        ],
        'student_camera' => 'Trainees come in with their camera',
        'student_camera_hint' => 'In the academy\'s session room. A trainee can always switch their camera on or off during the session.',
        'cameras' => [
            'off_for_minors' => 'Off for anyone under 18, on for everyone else',
            'off' => 'Off for every trainee',
        ],
    ],
    'observe' => 'Watch the session',
];
