<?php

return [
    'audience' => ['payer' => 'اللي بيدفع (ولي الأمر أو الطالب)'],
    'invoice_issued' => [
        'title' => 'فاتورة جديدة :number',
        'body' => 'فاتورة :student بمبلغ :total، آخر ميعاد للدفع :due.',
    ],
    'invoice_paid' => [
        'title' => 'وصلنا الدفع، شكرًا',
        'body' => 'فاتورة :number اتدفعت بالكامل (:total)، والحصص اتأكدت.',
    ],
];
