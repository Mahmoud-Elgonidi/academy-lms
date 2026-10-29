<?php

return [
    'audience' => ['payer' => 'الدافع (ولي الأمر أو الطالب)', 'accounts' => 'من يسجّل الدفعات (المالك والمدير والمحاسب)'],
    'invoice_issued' => [
        'title' => 'فاتورة جديدة :number',
        'body' => 'فاتورة :student بمبلغ :total، وآخر موعد للدفع :due. الفاتورة وطرق الدفع: :link',
    ],
    'invoice_paid' => [
        'title' => 'وصلنا الدفع، شكرًا',
        'body' => 'دُفعت الفاتورة :number بالكامل (:total)، وتأكدت الحصص.',
    ],
    'proof_received' => [
        'title' => 'إثبات دفع ينتظر المراجعة',
        'body' => 'أرسل :payer إثباتًا بأنه دفع :amount عبر :method للفاتورة :number (:student). تأكد من وصول المبلغ، ثم وافق أو ارفض.',
    ],
    'proof_approved' => [
        'title' => 'تأكدت دفعتك',
        'body' => 'وصلنا :amount على الفاتورة :number. المتبقي: :left.',
    ],
    'proof_rejected' => [
        'title' => 'لم نتمكن من تأكيد دفعتك',
        'body' => 'رُفض إثبات دفع :amount للفاتورة :number: :reason. يمكنك إرسال إثبات آخر: :link',
    ],
];
