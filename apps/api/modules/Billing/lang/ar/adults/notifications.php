<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../notifications.php.
// No guardians there: the payer is the trainee, so what they receive speaks to them directly.
return [
    'audience' => ['payer' => 'الدافع'],
    'invoice_issued' => [
        'body' => 'فاتورتك بمبلغ :total، وآخر موعد للدفع :due. الفاتورة وطرق الدفع: :link',
    ],
    'invoice_paid' => [
        'body' => 'دُفعت الفاتورة :number بالكامل (:total)، وتأكدت جلساتك.',
    ],
    'proof_received' => [
        'body' => 'أرسل :payer إثباتًا بأنه دفع :amount عبر :method للفاتورة :number (المتدرّب :student). تأكد من وصول المبلغ، ثم وافق أو ارفض.',
    ],
    'renewal_preview' => [
        'title' => 'تجديد باقتك قريبًا',
        'body' => 'بقيت جلسات قليلة في باقتك (:subject). وحين يصل الرصيد إلى :lessons تصدر فاتورة الباقة التالية «:plan» بنحو :amount، في :on تقريبًا. لا تحتاج إلى فعل شيء الآن.',
    ],
    'due_tomorrow' => [
        'body' => 'آخر موعد لدفع فاتورتك غدًا :due، والمتبقي منها :left. الفاتورة وطرق الدفع: :link',
    ],
    'due_today' => [
        'body' => 'يحلّ موعد دفع فاتورتك اليوم (:due)، والمتبقي منها :left. الفاتورة وطرق الدفع: :link',
    ],
    'overdue' => [
        'body' => 'مضى موعد دفع فاتورتك (:due)، والمتبقي منها :left. ادفعها حين تستطيع، أو راسل الأكاديمية إن احتجت إلى مساعدة: :link',
    ],
    'overdue_staff' => [
        'body' => 'لم يدفع :payer الفاتورة :number (المتدرّب :student) بعد يومين من موعدها، والمتبقي :left. وصله تذكير، فتابعوا معه إن لزم.',
    ],
    'lesson_at_risk' => [
        'title' => 'ادفع لتبقى جلستك',
        'body' => 'جلستك في :lesson لم تُدفع بعد، وتُلغى تلقائيًا في :until إن لم تُدفع الفاتورة :number (المتبقي :left). ادفع الآن لتبقى في موعدها: :link',
    ],
    'pay_link' => [
        'body' => 'أرسلت إليك الأكاديمية رابط دفع فاتورتك (المتبقي :left). الفاتورة وطرق الدفع: :link',
    ],
    'credits_expiring' => [
        'title' => 'جلساتك تنتهي صلاحيتها قريبًا',
        'body' => 'لديك في :subject :lessons دون موعد بعد، وتنتهي الصلاحية في :on. احجز المواعيد مع الأكاديمية قبل ذلك حتى لا يضيع شيء من رصيدك.',
    ],
    'renewal_coming' => [
        'title' => 'سنخصم فاتورتك من بطاقتك',
        'body' => 'صدرت فاتورة باقتك التالية (:number) بمبلغ :amount، وتُخصم تلقائيًا من البطاقة :card في :on. لإيقاف التجديد التلقائي قبل ذلك: :off',
    ],
    'charge_succeeded' => [
        'title' => 'جُدّدت باقتك تلقائيًا',
        'body' => 'خُصم :amount من البطاقة :card للفاتورة :number، وتأكدت جلساتك. الفاتورة: :link',
    ],
    'charge_retry' => [
        'title' => 'لم يتم خصم فاتورتك',
        'body' => 'لم يتم خصم :amount من البطاقة :card للفاتورة :number: :reason سنحاول مرة أخرى في :retry_at، ويمكنك الدفع الآن من الرابط: :link',
    ],
    'charge_failed' => [
        'title' => 'ادفع فاتورتك من الرابط',
        'body' => 'لم يتم خصم :amount من البطاقة :card للفاتورة :number: :reason ادفعها من الرابط حتى تبقى جلساتك في مواعيدها: :link',
    ],
    'auto_charge_skipped' => [
        'title' => 'لن تُخصم فاتورتك تلقائيًا',
        'body' => 'لن نخصم الفاتورة :number (:amount) تلقائيًا لأن :reason. ادفعها من الرابط: :link',
    ],
    'card_expiring' => [
        'title' => 'تنتهي صلاحية بطاقتك المحفوظة قريبًا',
        'body' => 'تنتهي صلاحية البطاقة :card في :on، وبعدها لا تُجدَّد باقتك تلقائيًا. اختر «احفظ بطاقتي» عند الدفع القادم ببطاقة جديدة: :link',
    ],
    'card_stopped' => [
        'title' => 'توقف التجديد التلقائي ببطاقتك',
        'body' => 'أوقفت الأكاديمية حساب الدفع المحفوظة عليه البطاقة :card، فلم يعد التجديد التلقائي بها ممكنًا. تصلك الفواتير برابط دفعها كالمعتاد: :link',
    ],
];
