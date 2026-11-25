<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'subtitle' => 'كل الجلسات بتوقيت :zone',
    'course' => 'جلسات البرنامج التدريبي في التقويم',
    'filters' => [
        'teacher' => 'كل المدرّبين',
        'student' => 'كل المتدرّبين',
        'subject' => 'كل المجالات',
    ],
    'totals' => [
        'lessons' => '{0} لا توجد جلسات|{1} جلسة واحدة|{2} جلستان|[3,10] :count جلسات|[11,*] :count جلسة',
        'hint' => 'اضغط على حالة لترى جلساتها فقط',
    ],
    'statuses' => [
        'student_absent' => 'غاب المتدرّب',
        'teacher_absent' => 'غاب المدرّب',
    ],
    'reasons' => [
        'school' => 'ظرف عمل',
        'family' => 'ظرف شخصي',
        'teacher_leave' => 'المدرّب في إجازة',
        'substitute_refused' => 'رفض المتدرّب المدرّب البديل',
    ],
    'by' => [
        'student' => 'من المتدرّب',
        'teacher' => 'من المدرّب',
        'family' => 'من الدافع',
    ],
    'legend' => [
        'off' => 'إجازة المدرّب',
    ],
    'more_hint' => ':count جلسات أخرى في هذا الوقت: افتح اليوم',
    'substitute' => 'مدرّب بديل',
    'empty' => [
        'title' => 'لا توجد جلسات هنا',
        'day' => 'لا توجد جلسات ولا مواعيد عمل في هذا اليوم.',
    ],
    'list' => [
        'more' => 'معروض أول :limit جلسة فقط. ضيّق التصفية، أو نزّل ملف Excel فيه كل الجلسات.',
    ],
    'summary' => [
        'teachers' => 'حسب المدرّب',
        'subjects' => 'حسب المجال',
        'teacher' => 'المدرّب',
        'subject' => 'المجال',
        'hint' => 'الساعات دون الجلسات الملغاة. اضغط على مدرّب أو مجال لترى تقويمه.',
        'empty' => 'لا توجد جلسات في هذه الفترة.',
    ],
    'details' => [
        'missing' => 'هذه الجلسة غير موجودة',
        'teacher' => 'المدرّب',
        'student' => 'المتدرّب',
        'subject' => 'المجال',
        'account' => 'حساب المتدرّب في البرنامج التدريبي',
        'teacher_calendar' => 'تقويم المدرّب',
        'student_calendar' => 'تقويم المتدرّب',
    ],
    'export' => [
        'lessons' => 'الجلسات',
        'title' => 'الجلسات من :from إلى :to',
        'teacher' => 'المدرّب',
        'subject' => 'المجال',
        'student' => 'المتدرّب',
        'instead_of' => 'بدلًا من المدرّب',
    ],
];
