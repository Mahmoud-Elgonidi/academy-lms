<?php

return [
    'audience' => [
        'family' => 'الطالب وأولياء أمره',
        'student' => 'الطالب',
        'teacher' => 'المدرس',
        'guardian' => 'أولياء أمور الطالب اللي بيستلموا التقارير',
    ],
    'report_submitted' => [
        'title' => 'تقرير حصة :student',
        'body' => ':teacher كتب تقرير حصة :when. التقييم :overall من 5. :note',
    ],
    'homework_assigned' => [
        'title' => 'واجب جديد لـ :student',
        'body' => ':title. آخر ميعاد للتسليم :due.',
    ],
    'homework_reviewed' => [
        'title' => 'اتصحح واجب :student',
        'body' => ':title: الدرجة :grade من 5. :feedback',
    ],
    'node_mastered' => [
        'title' => 'مبروك! :student اتقن درس جديد',
        'body' => 'اتقن ":lesson" وبقى خلّص :percent% من :program.',
    ],
    'homework_submitted' => [
        'title' => ':student سلّم الواجب',
        'body' => '":title" مستني تصحيحك.',
    ],
    'report_overdue' => [
        'title' => 'تقرير حصة :student متأخر',
        'body' => 'الحصة كانت :when. اكتب التقرير دلوقتي عشان الأسرة تعرف وصلوا لفين.',
    ],
    'no_grade' => 'من غير درجة',
    'cheer' => [
        'title' => ':from بيشجّعك!',
        'body' => ':from بعتلك :kind. كمّل كده!',
    ],
    'lesson_stars' => [
        'title' => ':stars من حصة :subject',
        'body' => ':how. نجومك اتضافت لرحلتك!',
    ],
    // The weekly note to guardians (Actions\Digest\SendWeeklyDigest): :body is the week, written already.
    'weekly_digest' => ['title' => 'أسبوع :student', 'body' => ':body'],
    'weekly_digest_voice' => ['title' => 'أسبوع :student', 'body' => ':body واسمع تسميع :student من الأسبوع ده: :link'],
];
