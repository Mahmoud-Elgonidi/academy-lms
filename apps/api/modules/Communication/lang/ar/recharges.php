<?php

// The academy's recharges, to the staff who see the plan (TellStaffAboutRecharges).
return [
    'how' => [
        'card' => 'بالبطاقة',
        'auto' => 'بالشحن التلقائي',
        'transfer' => 'بالتحويل',
    ],
    'declines' => [
        'declined' => 'رفض البنك العملية.',
        'insufficient_funds' => 'لا يكفي رصيد البطاقة.',
        'expired_card' => 'انتهت صلاحية البطاقة، وحُذفت من الأكاديمية.',
        'lost_card' => 'البطاقة موقوفة لدى البنك، وحُذفت من الأكاديمية.',
        'invalid_card' => 'لم تعد البطاقة صالحة، وحُذفت من الأكاديمية.',
        'authentication_required' => 'طلب البنك تأكيد العملية من صاحب البطاقة.',
        'processing_error' => 'تعذّرت العملية لدى البنك.',
    ],
    'then' => [
        'retry' => 'سنحاول مرة أخرى بعد يوم.',
        'off' => 'وتوقّف الشحن التلقائي، ويمكنكم شراء باقة أو تشغيله من جديد ببطاقة أخرى.',
    ],
    'off' => [
        'no_card' => 'لا توجد بطاقة محفوظة صالحة.',
        'no_price' => 'لم يعد للباقة المختارة سعر بعملة الأكاديمية.',
    ],
    'recharge_succeeded' => [
        'title' => 'أُضيف :amount إلى رصيد :meter',
        'body' => 'اكتمل دفع :price :how، ويبقى الرصيد المشترى 12 شهرًا. رقم الإيصال :receipt. التفاصيل: :link',
    ],
    'recharge_failed' => [
        'title' => 'تعذّر الشحن التلقائي لرصيد :meter',
        'body' => ':reason :then التفاصيل: :link',
    ],
    'auto_recharge_off' => [
        'title' => 'توقّف الشحن التلقائي لرصيد :meter',
        'body' => ':reason يمكنكم تشغيله من جديد من صفحة الاستهلاك والرصيد: :link',
    ],
];
