<?php

return [
    'lines' => [
        'block' => ':subject — :plan (الحزمة :block)',
        'extra' => ':subject — حصص إضافية (:minutes دقيقة للحصة)',
        'sibling' => 'خصم الإخوة (:rate)',
        'coupon' => 'كوبون :code (:value)',
        'late_join' => 'حصص فاتت قبل الانضمام إلى المجموعة (:count)',
        'shared' => 'باقة مشتركة',
    ],
    'statuses' => [
        'draft' => 'مسودة',
        'issued' => 'مستحقة',
        'paid' => 'مدفوعة',
        'void' => 'ملغاة',
        'overdue' => 'متأخرة',
        'partly_paid' => 'مدفوعة جزئيًا',
    ],
    'methods' => [
        'bank_transfer' => 'تحويل بنكي',
        'instapay' => 'InstaPay',
        'wallet' => 'محفظة إلكترونية',
        'cash' => 'نقدًا',
        'card_terminal' => 'بطاقة (جهاز دفع)',
        'other' => 'أخرى',
        'online' => 'دفع إلكتروني',
    ],
    'subscription_statuses' => [
        'active' => 'نشط',
        'past_due' => 'متأخر في الدفع',
        'paused' => 'متوقف مؤقتًا',
        'cancelled' => 'ملغى',
    ],
    // Why a block invoice waiting to be paid was voided when its course ended (on the invoice and its credit note).
    'void_course_ended' => 'انتهت الدورة',
    'errors' => [
        'no_price' => 'ليس لهذه الباقة سعر في هذه المنطقة. ضع لها سعرًا من "الباقات والأسعار".',
        'method' => 'اختر طريقة الدفع.',
        'not_payable' => 'هذه الفاتورة غير مستحقة، فلا يمكن تسجيل دفع عليها.',
        'currency' => 'الفاتورة بعملة :currency.',
        'amount' => 'يجب أن يكون المبلغ أكبر من صفر ولا يزيد على المتبقي (:max).',
        'not_voidable' => 'يمكنك إلغاء فاتورة مستحقة لا يوجد عليها أي دفع فقط.',
        'void_proof' => 'يوجد إيصال تحويل ينتظر المراجعة على هذه الفاتورة: وافق عليه أو ارفضه أولًا.',
        'void_checkout' => 'يوجد دفع إلكتروني جارٍ على هذه الفاتورة: انتظر حتى يكتمل أو تنتهي مهلته.',
    ],
];
