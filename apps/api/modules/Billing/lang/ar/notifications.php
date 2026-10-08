<?php

return [
    'audience' => ['payer' => 'اللي بيدفع (ولي الأمر أو الطالب)', 'accounts' => 'اللي بيسجّل الدفعات (المالك والمدير والمحاسب)'],
    'invoice_issued' => [
        'title' => 'فاتورة جديدة :number',
        'body' => 'فاتورة :student بمبلغ :total، آخر ميعاد للدفع :due. الفاتورة وطرق الدفع: :link',
    ],
    'invoice_paid' => [
        'title' => 'وصلنا الدفع، شكرًا',
        'body' => 'فاتورة :number اتدفعت بالكامل (:total)، والحصص اتأكدت.',
    ],
    'proof_received' => [
        'title' => 'إثبات دفع مستني مراجعة',
        'body' => ':payer بعت إثبات إنه دفع :amount بـ :method لفاتورة :number (:student). اتأكد إن الفلوس وصلت، وبعدين وافق أو ارفض.',
    ],
    'proof_approved' => [
        'title' => 'دفعتك اتأكدت',
        'body' => 'وصلنا :amount على فاتورة :number. الباقي: :left.',
    ],
    'proof_rejected' => [
        'title' => 'ماقدرناش نأكد دفعتك',
        'body' => 'إثبات دفع :amount لفاتورة :number اترفض: :reason. تقدر تبعت إثبات تاني: :link',
    ],
];
