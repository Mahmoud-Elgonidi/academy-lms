<?php

return [
    'lines' => [
        'block' => ':subject — :plan (البلوك :block)',
        'extra' => ':subject — حصص زيادة (:minutes دقيقة للحصة)',
        'sibling' => 'خصم الإخوات (:rate)',
        'coupon' => 'كوبون :code (:value)',
        'shared' => 'باقة مشتركة',
    ],
    'statuses' => [
        'draft' => 'مسودة',
        'issued' => 'مستحقة',
        'paid' => 'مدفوعة',
        'void' => 'ملغاة',
        'overdue' => 'متأخرة',
        'partly_paid' => 'مدفوع جزء',
    ],
    'methods' => [
        'bank_transfer' => 'تحويل بنكي',
        'instapay' => 'InstaPay',
        'wallet' => 'محفظة إلكترونية',
        'cash' => 'كاش',
        'card_terminal' => 'كارت (ماكينة)',
        'other' => 'أخرى',
        'online' => 'دفع إلكتروني',
    ],
    'subscription_statuses' => [
        'active' => 'نشط',
        'past_due' => 'متأخر في الدفع',
        'paused' => 'متوقف مؤقتًا',
        'cancelled' => 'ملغي',
    ],
    // Why a block invoice waiting to be paid was voided when its course ended (on the invoice and its credit note).
    'void_course_ended' => 'الكورس انتهى',
    'errors' => [
        'no_price' => 'الباقة دي مالهاش سعر في المنطقة دي. حط لها سعر من "الباقات والأسعار".',
        'method' => 'اختار طريقة الدفع.',
        'not_payable' => 'الفاتورة دي مش مستحقة، فمينفعش يتسجل عليها دفع.',
        'currency' => 'الفاتورة بعملة :currency.',
        'amount' => 'المبلغ لازم يكون أكبر من صفر وميزدش عن المتبقي (:max).',
        'not_voidable' => 'ينفع تلغي فاتورة مستحقة مفيش عليها أي دفع بس.',
        'void_proof' => 'فيه إيصال تحويل مستني على الفاتورة دي: وافق عليه أو ارفضه الأول.',
        'void_checkout' => 'فيه دفع أونلاين شغال على الفاتورة دي: استنى لما يخلص أو تخلص مهلته.',
    ],
];
