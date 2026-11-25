<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'statuses' => [
        'forming' => 'تنتظر متدرّبيها',
    ],
    'late_join' => [
        'private' => 'جلسات تعويض فردية مع المدرّب',
        'group' => 'جلسات تعويض في مجموعة أخرى',
    ],
    'errors' => [
        'no_groups' => 'هذا المدرّب لا يدرّب مجموعات.',
        'over_teacher' => 'لا تزيد المجموعة عن :max متدرّبين عند هذا المدرّب.',
        'below_members' => 'في المجموعة :count متدرّبين؛ لا تقلّ المقاعد عن ذلك.',
        'min_to_start' => 'عدد المتدرّبين اللازم لبدء المجموعة من 1 إلى :capacity.',
        'session_minutes' => 'مدة الجلسة من دقيقة إلى 240 دقيقة.',
        'already' => 'المتدرّب في هذه المجموعة.',
        'plan_shape' => 'جلسات المجموعة :minutes دقيقة، :per_week في الأسبوع؛ اختر باقة بهذا الشكل.',
        'plan_is_group' => 'هذه باقة جماعية؛ الجلسات الفردية تحتاج باقة فردية.',
        'minors_adults' => 'لا تجمع هذه المجموعة فئات عمرية مختلفة.',
        'level' => 'مستوى المتدرّب غير مستوى المجموعة.',
        'late_join' => 'اختر طريقة التعويض عن الجلسات الفائتة.',
        'no_group_makeup' => 'لا يوجد رصيد تعويض للجلسات الجماعية.',
        'not_group_lesson' => 'هذه ليست جلسة مجموعة.',
        'lesson_closed' => 'لم تعد هذه الجلسة متاحة.',
        'other_subject' => 'هذه الجلسة لمجال آخر.',
        'own_group' => 'هذه جلسة مجموعة المتدرّب نفسها.',
        'student_busy' => 'لدى المتدرّب جلسة أخرى في هذا الوقت.',
    ],
];
