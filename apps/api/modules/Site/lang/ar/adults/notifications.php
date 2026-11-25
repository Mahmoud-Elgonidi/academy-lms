<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// request_received says what request_received_adults says: the whole academy speaks to the one asking for themself.
return [
    'audience' => [
        'requester' => 'من طلب الجلسة التعريفية من صفحة الأكاديمية (بالواتساب والبريد، فليس له حساب بعد)',
        'staff' => 'من يردّون على الطلب (المبيعات للجلسات التعريفية، ومسؤولو المدرّبين لطلبات التدريب)',
    ],
    'kinds' => ['trial' => 'جلسة تعريفية', 'teach' => 'التدريب معنا'],
    'request_received' => [
        'body' => 'شكرًا لاهتمامك بالجلسة التعريفية المجانية. سنتواصل معك خلال :hours ساعات. :next',
        'pick' => 'وإن شئت فاختر موعد جلستك بنفسك من هنا: :link',
        'wait' => 'وسنتفق معك على موعد يناسبك.',
    ],
];
