<?php

return [
    'audience' => [
        'family' => 'الطالب وأولياء أمره',
        'student' => 'الطالب',
        'teacher' => 'المعلّم',
        'guardian' => 'أولياء أمور الطالب الذين يستلمون التقارير',
    ],
    'report_submitted' => [
        'title' => 'تقرير حصة :student',
        'body' => 'كتب :teacher تقرير حصة :when. التقييم :overall من 5. :note',
    ],
    'homework_assigned' => [
        'title' => 'واجب جديد لـ :student',
        'body' => ':title. آخر موعد للتسليم :due.',
    ],
    'homework_reviewed' => [
        'title' => 'صُحّح واجب :student',
        'body' => ':title: الدرجة :grade من 5. :feedback',
    ],
    'node_mastered' => [
        'title' => 'مبارك! أتقن :student درسًا جديدًا',
        'body' => 'أتقن ":lesson" وأنهى :percent% من :program.',
    ],
    'homework_submitted' => [
        'title' => 'سلّم :student الواجب',
        'body' => '":title" ينتظر تصحيحك.',
    ],
    'report_overdue' => [
        'title' => 'تقرير حصة :student متأخر',
        'body' => 'كانت الحصة :when. اكتب التقرير الآن لتعرف الأسرة أين وصلوا.',
    ],
    'no_grade' => 'من دون درجة',
    'cheer' => [
        'title' => ':from يشجّعك!',
        'body' => 'أرسل لك :from :kind. استمر هكذا!',
    ],
    'lesson_stars' => [
        'title' => ':stars من حصة :subject',
        'body' => ':how. أُضيفت نجومك إلى رحلتك!',
    ],
    // The weekly note to guardians (Actions\Digest\SendWeeklyDigest): :body is the week, written already.
    'weekly_digest' => ['title' => 'أسبوع :student', 'body' => ':body'],
    'weekly_digest_voice' => ['title' => 'أسبوع :student', 'body' => ':body واستمع إلى تسميع :student من هذا الأسبوع: :link'],
];
