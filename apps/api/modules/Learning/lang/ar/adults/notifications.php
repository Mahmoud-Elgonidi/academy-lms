<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// No guardians there, so what went to "the student and their guardians" reaches the trainee alone: written to them.
return [
    'audience' => [
        'family' => 'المتدرّب',
        'student' => 'المتدرّب',
        'teacher' => 'المدرّب',
        'guardian' => 'لا أحد (لا أولياء أمور في أكاديمية الكبار)',
    ],
    'report_submitted' => [
        'title' => 'تقرير جلستك',
        'body' => 'كتب :teacher تقرير جلسة :when. التقييم :overall من 5. :note',
    ],
    'homework_assigned' => [
        'title' => 'مهمة جديدة لك',
        'body' => ':title. آخر موعد للتسليم :due.',
    ],
    'homework_reviewed' => [
        'title' => 'صُحّحت مهمتك',
        'body' => ':title: الدرجة :grade من 5. :feedback',
    ],
    'node_mastered' => [
        'title' => 'أحسنت! أتقنت درسًا جديدًا',
        'body' => 'أتقنت ":lesson" وأنهيت :percent% من :program.',
    ],
    'homework_submitted' => [
        'title' => 'سلّم :student المهمة',
    ],
    'report_overdue' => [
        'title' => 'تقرير جلسة :student متأخر',
        'body' => 'كانت الجلسة :when. اكتب التقرير الآن ليعرف المتدرّب أين وصل.',
    ],
    'lesson_stars' => [
        'title' => ':stars من جلسة :subject',
    ],
];
