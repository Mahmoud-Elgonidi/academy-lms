<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../policy.php.
return [
    'subtitle' => 'القواعد ثابتة، وهذه الأرقام تضبطها. كل حفظ يُنشئ إصدارًا جديدًا، ويُسجَّل مع كل قرار على جلسة رقم الإصدار الذي طُبّق.',
    'sections' => [
        'student' => 'إلغاء المتدرّب وتأجيله',
        'teacher' => 'إلغاء المدرّب وأجره',
        'credit' => 'التعويض والجلسات غير المدفوعة',
    ],
    'fields' => [
        'student_notice_hours' => 'مهلة إلغاء المتدرّب أو تأجيله (ساعات)',
        'teacher_notice_hours' => 'مهلة إلغاء المدرّب (ساعات)',
        'late_cancel_teacher_pay_pct' => 'أجر المدرّب إن ألغى المتدرّب متأخرًا (%)',
        'student_absent_teacher_pay_pct' => 'أجر المدرّب إن غاب المتدرّب (%)',
        'unpaid_cancel_hours' => 'إلغاء الجلسة غير المدفوعة قبلها بـ (ساعات)',
        'teacher_late_after_min' => 'يُحتسب المدرّب متأخرًا بعد (دقائق)',
        'teacher_absent_after_min' => 'يُحتسب المدرّب غائبًا بعد (دقائق)',
        'student_wait_short_min' => 'انتظار المتدرّب في جلسة 30 دقيقة (دقائق)',
        'student_wait_long_min' => 'انتظار المتدرّب في جلسة 45 دقيقة أو أكثر (دقائق)',
        'completion_pct' => 'تُحتسب الجلسة مكتملة إن نُفّذ منها (%)',
    ],
    'hints' => [
        'quota_per_block' => 'بعدها يُضيّع الإلغاءُ الجلسة (يمكن للإدارة الاستثناء)، ويُغلق التأجيل.',
        'unpaid_cancel_hours' => 'للمتدرّبين المسجّلين على باقة فقط. 0 = لا إلغاء تلقائي.',
    ],
    'rules' => [
        'credit' => 'رصيد المتدرّب',
        'teacher_pay' => 'أجر المدرّب',
    ],
    'events' => [
        'student_reschedule' => 'المتدرّب يؤجّل',
        'student_cancel' => 'المتدرّب يلغي',
        'teacher_cancel' => 'المدرّب يلغي',
        'unpaid' => 'جلسة غير مدفوعة',
        'student_absent' => 'غاب المتدرّب',
        'teacher_absent' => 'غاب المدرّب',
        'course_stopped' => 'أُوقف البرنامج أو انتهى',
    ],
    'conditions' => [
        'session_type' => 'جلسة :type',
    ],
    'kpi' => [
        'late_student_cancel' => 'إلغاء متأخر من المتدرّب',
        'student_absent' => 'غياب المتدرّب',
        'teacher_cancel' => 'إلغاء من المدرّب',
        'teacher_late_cancel' => 'إلغاء متأخر من المدرّب',
        'teacher_absent' => 'غياب المدرّب',
    ],
    'asked_by' => [
        'student' => 'المتدرّب',
        'teacher' => 'المدرّب',
        'family' => 'صاحب الحساب',
    ],
    'reasons' => [
        'school' => 'ارتباط عمل أو اختبارات',
        'family' => 'ظرف شخصي',
        'teacher_leave' => 'إجازة المدرّب',
        'substitute_refused' => 'رُفض المدرّب البديل',
        'enrollment_paused' => 'أُوقف البرنامج',
        'enrollment_ended' => 'انتهى البرنامج',
    ],
    'preview' => [
        'credit' => [
            'refund' => 'تعود الدقائق إلى رصيد المتدرّب.',
            'forfeit' => 'تضيع الجلسة على المتدرّب (تُخصم).',
            'makeup' => 'يحصل المتدرّب على رصيد تعويض صالح :days يوم.',
            'consumed' => 'تُخصم الجلسة.',
        ],
        'teacher_pay' => 'أجر المدرّب: :pct%',
        'rule' => 'القاعدة :n · الإصدار :v · قبل الجلسة بـ :hours ساعة · استُخدم :used من :quota في الدفعة',
    ],
    'refusals' => [
        'too_late' => 'يجب أن يكون التأجيل قبل الجلسة بـ :hours ساعة على الأقل.',
        'quota' => 'استخدم المتدرّب :n مرات الإلغاء والتأجيل في هذه الدفعة.',
    ],
    'errors' => [
        'not_cleared' => 'لا يمكن لهذا المدرّب أخذ جلسات جديدة الآن (:state).',
        'not_movable' => 'لا يمكن تأجيل هذه الجلسة: بدأت أو أُغلقت.',
        'teacher_busy' => 'المدرّب غير متاح أو خارج مواعيد عمله في هذا الوقت.',
        'someone_busy' => 'لدى المدرّب أو المتدرّب جلسة أخرى في هذا الوقت.',
        'no_extra_credit' => 'ليس في البرنامج دقائق مدفوعة كافية صالحة في هذا اليوم (رصيد التعويض للجلسات التعويضية فقط).',
        'course_not_running' => 'لا جلسات لهذا البرنامج الآن (لم يبدأ أو موقوف أو انتهى).',
        'same_teacher' => 'هذا هو المدرّب الحالي نفسه.',
        'new_teacher_busy' => 'المدرّب الجديد غير متاح في: :dates',
    ],
];
