<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../payments.php.
// The payer reads in the singular.
return [
    'page' => [
        'student' => 'المتدرّب',
        'coupon' => [
            'title' => 'لديك كوبون خصم؟',
            'offer' => 'عرضك بعد الجلسة التعريفية (:code) مطبّق على هذه الفاتورة.',
            'hint' => 'تصدر الفاتورة من جديد بعد الخصم، ثم تدفعها.',
        ],
        'result_hint' => [
            'paid' => 'تأكدت جلساتك، وسيصلك الإيصال في رسالة.',
        ],
    ],
    'settings' => [
        'empty' => 'لا توجد بوابات بعد. أضف بوابة ليدفع المتدرّبون إلكترونيًا.',
    ],
];
