<?php

// The adults academies' words (docs/architecture/02, section 29): only the keys that read differently there.
return [
    'kpis' => [
        'today' => 'جلسات اليوم',
        'absences_hint' => ':teacher مدرّب · :student متدرّب',
    ],
    'now_and_next' => 'الجلسات الآن وخلال الساعة القادمة',
    'filter' => 'تصفية الجلسات',
    'filter_empty' => 'لا توجد جلسات هنا الآن.',
    'cols' => [
        'teacher' => 'المدرّب',
        'student' => 'المتدرّب',
        'subject' => 'المجال',
    ],
    'groups' => [
        'done_hint' => ':count جلسة',
    ],
    'status' => [
        'late' => 'المدرّب متأخر',
        'waiting' => 'بانتظار المتدرّب',
    ],
    'status_names' => [
        'student_absent' => 'غياب المتدرّب',
        'teacher_absent' => 'غياب المدرّب',
    ],
    'checks' => [
        'teacher_free' => 'المدرّب متاح',
        'teacher_busy' => 'المدرّب في جلسة قبلها',
    ],
    'issues' => [
        'teacher_not_joined' => [
            'title' => 'لم يدخل :teacher الجلسة',
            'context' => ':base · المتدرّب ينتظر منذ :time',
            'rule' => 'غياب مدرّب + جلسة تعويضية لـ :student',
        ],
        'nobody_joined' => [
            'title' => 'لم يدخل أحد جلسة :student',
        ],
        'student_not_joined' => [
            'title' => 'لم يدخل :student الجلسة',
            'context' => ':base · المدرّب ينتظر منذ :time',
            'rule' => 'غياب متدرّب وتُخصم الجلسة من الباقة',
        ],
        'weak_connection' => [
            'title' => 'اتصال ضعيف في جلسة :student',
            'rule' => 'إن كان وقت التدريب الفعلي أقل من 75%: تُراجع الجلسة ولا يُظلم أحد',
        ],
        'unfunded' => [
            'title' => 'جلسة :student غير مدفوعة',
            'rule' => 'ستبدأ الجلسة دون رصيد',
        ],
        'needs_review' => [
            'title' => 'جلسة :student تحتاج إلى قرارك',
        ],
    ],
    'review_reasons' => [
        'short_delivery' => 'وقت التدريب الفعلي أقل من 75%',
    ],
    'results' => [
        'student_absent' => 'غياب المتدرّب',
        'teacher_absent' => 'غياب المدرّب',
    ],
    'clocks' => [
        'student' => 'لدى المتدرّب',
        'teacher' => 'لدى المدرّب',
    ],
    'detail' => [
        'people' => 'الدافع والباقة',
    ],
    'relationships' => [
        'parent' => 'الدافع',
        'father' => 'جهة التواصل',
        'mother' => 'جهة التواصل',
        'guardian' => 'الدافع',
    ],
    'targets' => [
        'teacher' => 'المدرّب',
        'student' => 'المتدرّب',
        'guardian' => 'الدافع',
    ],
    'actions' => [
        'call_teacher' => 'اتصل بالمدرّب',
        'call_guardian' => 'اتصل بالدافع',
        'call_student' => 'اتصل بالمتدرّب',
        'reassure' => 'طمئن المتدرّب',
        'cancel' => 'ألغِ الجلسة',
        'mark_student_absent' => 'سجّل غياب المتدرّب',
        'extend' => 'مدّد الجلسة',
        'substitute' => 'مدرّب بديل',
    ],
    'hints' => [
        'call_guardian_any' => 'لأي تأكيد قبل الجلسة',
        'reassure' => 'أخبر المتدرّب أن المدرّب في الطريق',
        'cancel_makeup' => 'يحصل :student على جلسة تعويضية، ولا تُحتسب الجلسة',
        'cancel_unpaid' => 'تُلغى الجلسة دون خصم',
        'mark_student_absent' => 'تُغلق الجلسة غيابَ متدرّب وتُخصم من الباقة، ويأخذ المدرّب أجره',
        'extend' => '5 أو 10 أو 15 دقيقة إن لم يكن لدى أحد جلسة بعدها',
        'flag_technical' => 'تتحوّل الجلسة إلى المراجعة ويبقى الرصيد محجوزًا حتى تقرّر',
        'allow_unfunded' => 'تتم الجلسة وتتابع الإدارة الدفع بعدها',
        'dispute' => 'تعود الجلسة إلى المراجعة، ويُصحَّح الرصيد والراتب مع القرار',
        'observe' => 'يفتح الجلسة في تبويب جديد دون أن يراك أحد أو يسمعك.',
        'resend_link' => 'يرسل إلى :student رابط الدخول مرة أخرى، حتى في الأوقات الهادئة.',
        'soon_classroom' => 'يُفعَّل مع قاعة الجلسات المباشرة',
        'soon_substitute' => 'يُفعَّل مع البحث عن مدرّب',
    ],
    'cancel' => [
        'heading' => 'إلغاء الجلسة',
        'makeup' => 'يحصل المتدرّب على جلسة تعويضية',
        'submit' => 'ألغِ الجلسة',
    ],
    'cancel_reasons' => [
        'teacher_absent' => 'غاب المدرّب',
        'student_request' => 'طلب المتدرّب',
    ],
    'resolve' => [
        'outcomes' => [
            'completed' => 'اكتملت (تُخصم، ويأخذ المدرّب أجره)',
            'teacher_absent' => 'غياب المدرّب (تعويض للمتدرّب)',
            'student_absent' => 'غياب المتدرّب (تُخصم)',
        ],
    ],
    'done' => [
        'cancelled' => 'أُلغيت الجلسة.',
        'excused' => 'أُعفي مقعد المتدرّب، وتستمر الجلسة لبقية المجموعة.',
        'student_absent' => 'سُجّل غياب المتدرّب.',
        'extended' => 'مُدّدت الجلسة :min دقائق.',
        'flagged' => 'حُوّلت الجلسة إلى المراجعة.',
        'allowed' => 'ستتم الجلسة استثناءً.',
    ],
    'log' => [
        'joined_teacher' => 'دخل المدرّب',
        'joined_student' => 'دخل المتدرّب',
        'left' => 'خروج من قاعة الجلسة',
        'decision_allow_unfunded' => 'سمح :actor بالجلسة دون دفع',
        'decision_student_added' => 'انضم متدرّب إلى الجلسة',
        'decision_student_removed' => 'خرج متدرّب من الجلسة',
        'teacher_changed' => 'نقلها :actor إلى مدرّب آخر',
        'extended' => 'مدّد :actor الجلسة :min دقائق',
    ],
    'empty' => [
        'title' => 'لا توجد جلسات الآن ولا خلال الساعة القادمة',
        'hint' => 'تظهر هنا تلقائيًا الجلسات التي تبدأ خلال ساعة.',
    ],
    'errors' => [
        'double_booked' => 'لدى المدرّب أو المتدرّب جلسة في الوقت نفسه.',
        'cannot_cancel' => 'لا يمكن إلغاء هذه الجلسة في حالتها الحالية.',
        'not_student_absent' => 'يُسجَّل الغياب عندما يكون المدرّب قد دخل والمتدرّب لم يدخل.',
        'cannot_extend' => 'لا يمكن تمديد هذه الجلسة.',
        'extend_overlap' => 'لدى المدرّب أو المتدرّب جلسة بعدها مباشرة.',
        'cannot_flag' => 'لا يمكن تحويل هذه الجلسة إلى المراجعة الآن.',
        'cannot_resolve' => 'هذه الجلسة لا تحتاج إلى قرار.',
        'changed' => 'غيّر شخص آخر هذه الجلسة للتو. اطّلع عليها مرة أخرى ثم حاول.',
    ],
];
