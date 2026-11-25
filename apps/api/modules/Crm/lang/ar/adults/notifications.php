<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// No guardians there: what went to the family reaches the trainee, written to them.
return [
    'audience' => [
        'teacher' => 'مدرّب الجلسة التعريفية',
        'family' => 'المتدرّب نفسه',
    ],
    'assess_trial' => ['title' => 'قيّم الجلسة التعريفية', 'body' => ':student · :subject · :when. اكتب المستوى والباقة التي تنصح بها ليكمل فريق المبيعات.'],
    'trial_missed' => ['title' => 'لم تتم الجلسة التعريفية', 'body' => ':name: :outcome. تواصل معه واحجز موعدًا آخر.'],
    'trial_assessed' => ['title' => 'تقييم :name جاهز', 'body' => ':teacher: :level. حان وقت الحديث معه عن الاشتراك.'],
    'trial_unassessed' => ['title' => 'تقييم :name لم يُكتب بعد', 'body' => 'مرّ يوم على جلسته التعريفية مع :teacher دون تقييم. ذكّر المدرّب ليكتبه، فالمتدرّب ينتظر الخطوة التالية.'],
    'trial_cancelled' => ['title' => 'ألغى :name جلسته التعريفية', 'body' => 'كانت يوم :when. تواصل معه واحجز موعدًا آخر.'],
    'trial_report_ready' => ['title' => 'تقرير جلستك التعريفية جاهز', 'body' => 'كتب :teacher تقريره عن جلستك التعريفية في :subject: المستوى، ونقاط القوة، وما تحتاج إلى العمل عليه، والباقة المقترحة. اقرأه من هنا: :link :offer'],
    'trial_offer_ending' => ['title' => 'عرضك ينتهي قريبًا', 'body' => 'خصم :percent% على أول باقة لك ينتهي في :until. تجد التفاصيل وطريقة البدء في التقرير: :link'],
    'trial_missed_family' => ['title' => 'افتقدناك في الجلسة التعريفية', 'body' => 'لم تحضر جلسة :subject التعريفية في :when. :next'],
    'trial_sorry_family' => ['title' => 'نعتذر عن جلستك التعريفية', 'body' => 'تعذّر عقد جلستك التعريفية في :subject في :when من جهتنا، ونعتذر لك. لا تُحسب هذه الجلسة من جلستك التعريفية المجانية. :next'],
    'next' => [
        'link' => 'اختر موعدًا آخر يناسبك من هنا: :link',
        'call' => 'سنتواصل معك لنتفق على موعد آخر.',
    ],
];
