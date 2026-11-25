<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../billing.php.
return [
    'lines' => [
        'extra' => ':subject — extra sessions (:minutes min each)',
        'sibling' => 'Multi-trainee discount (:rate)',
        'late_join' => 'Sessions held before joining the group (:count)',
    ],
    'void_course_ended' => 'The programme ended',
];
