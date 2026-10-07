<?php

return [
    // By the action's two parts: 'billing.gateway_saved' is actions.billing.gateway_saved.
    'actions' => [
        'billing' => [
            'gateway_saved' => 'حفظ حساب الدفع ":label" (:gateway · :mode)',
            'payment_recorded' => 'تسجيل دفعة :amount (:method) على الفاتورة :number',
            'invoice_voided' => 'إلغاء الفاتورة :number: :reason',
            'settings_saved' => 'تعديل إعدادات الضريبة والفواتير',
            'stripe_connected' => 'ربط حساب Stripe :account (:mode)',
            'stripe_disconnected' => 'فصل حساب Stripe :account',
        ],
        'policy' => [
            'saved' => 'إصدار :version من سياسة الإلغاء والتعويض',
        ],
        'session' => [
            'cancel_waived' => 'إلغاء حصة باستثناء من السياسة',
            'review_resolved' => 'حسم حصة تحت المراجعة: :outcome',
        ],
        'shift' => [
            'approved_override' => 'اعتماد شفتات من غير موافقة المدرس: :reason',
        ],
        'enrollment' => [
            'teacher_changed' => 'تغيير مدرس طالب من :starting',
        ],
        'payroll' => [
            'month_closed' => 'قفل مرتبات :month',
            'adjusted' => ':kind :amount: :note',
            'adjustment_reversed' => 'إلغاء :kind :amount',
            'rates_saved' => 'حفظ جدول الأسعار ":name"',
        ],
        'import' => [
            'done' => 'استيراد :file',
        ],
        'identity' => [
            'password_set' => ':name حدد كلمة السر من لينك الترحيب',
            'password_changed' => 'تغيير كلمة السر',
        ],
        'impersonation' => [
            'started' => 'دخول بحساب :user لمدة :minutes دقيقة: :reason',
            'ended' => 'انتهى الدخول بحساب :user: :how',
        ],
        'academy' => [
            'created' => 'إنشاء الأكاديمية، والمالك :owner',
            'suspended' => 'إيقاف الأكاديمية: :reason',
            'reactivated' => 'تشغيل الأكاديمية تاني',
        ],
        'platform' => [
            'signed_in' => 'دخول على لوحة المنصة',
        ],
    ],
    'values' => [
        'method' => ['bank_transfer' => 'تحويل بنكي', 'instapay' => 'InstaPay', 'wallet' => 'محفظة إلكترونية', 'cash' => 'كاش', 'card_terminal' => 'ماكينة كارت', 'other' => 'أخرى', 'online' => 'دفع إلكتروني'],
        'kind' => ['bonus' => 'مكافأة', 'deduction' => 'خصم'],
        'outcome' => ['completed' => 'اتعملت', 'teacher_absent' => 'غياب المدرس', 'student_absent' => 'غياب الطالب', 'cancelled_makeup' => 'اتلغت بتعويض'],
        'mode' => ['test' => 'تجريبي', 'live' => 'حقيقي'],
        'how' => ['exit' => 'خرج بنفسه', 'expired' => 'الوقت خلص', 'ended' => 'اتقفل من لوحة المنصة'],
    ],
    'groups' => [
        'billing' => 'الفلوس',
        'scheduling' => 'الحصص والسياسات',
        'payroll' => 'المرتبات',
        'import' => 'الاستيراد',
        'identity' => 'الحسابات',
        'impersonation' => 'دخول فريق المنصة',
        'platform' => 'المنصة',
    ],
    'actor' => [
        'system' => 'النظام',
        'platform' => 'فريق المنصة · :name',
        'platform_as' => 'فريق المنصة · :name، بحساب :user',
    ],
];
