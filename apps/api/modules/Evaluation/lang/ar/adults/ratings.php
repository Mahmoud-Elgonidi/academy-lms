<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from the base file of the same name.
return [
    'ask' => 'ما رأيك في الجلسة؟',
    'tags' => [
        'engaging' => 'الجلسة مفيدة',
        'late' => 'تأخر المدرّب',
    ],
    'page' => [
        'title' => 'قيّم الجلسة',
        'heading' => 'كيف كانت جلستك؟',
        'change' => 'يمكنك تغيير تقييمك في أي وقت خلال أسبوعين من الجلسة.',
        'private' => 'يرى المدرّب التقييمات من دون أسماء.',
    ],
    'errors' => [
        'not_yours' => 'هذه الجلسة ليست لك.',
        'not_held' => 'هذه الجلسة لم تتم، فلا يمكن تقييمها.',
        'too_old' => 'تُقيَّم الجلسات خلال :days يومًا فقط.',
    ],
];
