<?php

return [
    'audience' => [
        'family' => 'الطالب وأولياء أمره',
        'teacher' => 'المدرس',
        'supervisors' => 'المشرفين والمنسقين',
        'reviewers' => 'اللي بيراجعوا الشفتات',
    ],
    'reminder_day' => ['title' => 'حصة :subject بكرة', 'body' => 'حصة :student مع :teacher :when.'],
    'reminder_hour' => ['title' => 'حصة :subject بعد ساعة', 'body' => 'حصة :student مع :teacher الساعة :when. ادخلوا من التطبيق قبلها بدقيقتين.'],
    'reminder_hour_teacher' => ['title' => 'حصة :student بعد ساعة', 'body' => 'حصة :subject :when.'],
    'join_now' => ['title' => 'الحصة بدأت!', 'body' => ':teacher مستني :student في حصة :subject. افتحوا التطبيق وادخلوا دلوقتي.'],
    'teacher_join_now' => ['title' => 'حصة :student بدأت', 'body' => 'الحصة بدأت :when ولسه ما دخلتش. ادخل دلوقتي.'],
    'teacher_late' => ['title' => ':teacher ما دخلش حصة :student', 'body' => 'الحصة بدأت :when. تابعها من لوحة العمليات.'],
    'session_cancelled' => ['title' => 'اتلغت حصة :subject', 'body' => 'حصة :student يوم :when اتلغت. :outcome'],
    'session_cancelled_teacher' => ['title' => 'اتلغت حصة :student', 'body' => 'حصة :subject يوم :when اتلغت.'],
    'session_moved' => ['title' => 'حصة :subject اتنقلت', 'body' => 'حصة :student اتنقلت من :from لـ :when.'],
    'session_moved_teacher' => ['title' => 'حصة :student اتنقلت', 'body' => 'حصة :subject اتنقلت من :from لـ :when.'],
    'proposal_submitted' => ['title' => ':teacher بعت مقترح شفتات', 'body' => 'راجعه من صفحة "مقترحات الشفتات".'],
    'proposal_answered' => ['title' => 'رد على مقترح شفتاتك', 'body' => ':outcome'],
    'outcomes' => [
        'makeup' => 'وليكم حصة تعويض.',
        'refund' => 'والرصيد رجع لحسابكم.',
        'forfeit' => 'والحصة اتحسبت حسب سياسة الإلغاء.',
        'consumed' => 'والحصة اتحسبت حسب سياسة الإلغاء.',
        'unpaid' => 'عشان الفاتورة ما اتدفعتش. ادفعوها وهنرجّع المواعيد.',
        'none' => '',
    ],
    'answers' => [
        'approved' => 'اتعتمد. الجدول الجديد بيبدأ من التاريخ اللي اخترته.',
        'rejected' => 'اترفض. شوف ملاحظة الإدارة في التطبيق.',
        'changes_requested' => 'الإدارة طلبت تعديل. شوف ملاحظتهم وابعته تاني.',
        'countered' => 'الإدارة بعتت جدول معدّل. وافق عليه أو عدّله.',
    ],
];
