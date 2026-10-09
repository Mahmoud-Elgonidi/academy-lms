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
            'proof_rejected' => 'رفض إثبات دفع :amount على الفاتورة :number: :reason',
            'coupon_saved' => 'حفظ الكوبون :code (:value)',
            'coupon_applied' => 'تطبيق الكوبون :code (:value) على اشتراك',
            'coupon_removed' => 'شيل الكوبون :code من اشتراك',
            'sibling_discount_saved' => 'خصم الإخوات: التاني :second%، والتالت وبعده :more%',
            'extra_lessons' => 'فاتورة :number بـ :lessons حصص زيادة (:total)',
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
            'contact_changed' => 'تغيير إيميل أو رقم :name (طرق الدخول)',
            'account_disabled' => 'قفل حساب :name',
            'account_enabled' => 'فتح حساب :name تاني',
            'password_set' => ':name حدد كلمة السر من لينك الترحيب',
            'password_changed' => 'تغيير كلمة السر',
            'two_factor_enabled' => ':name شغّل الدخول بخطوتين',
            'two_factor_disabled' => ':name قفل الدخول بخطوتين',
            'recovery_code_used' => ':name دخل بكود استرجاع',
            'recovery_codes_renewed' => ':name عمل أكواد استرجاع جديدة',
            'two_factor_reset' => 'فريق المنصة صفّر الدخول بخطوتين لـ :name: :reason',
            'social_linked' => ':name ربط حساب :provider (:via)',
            'social_unlinked' => ':name فصل حساب :provider',
        ],
        'people' => [
            'consent_granted' => ':guardian وافق على حساب :student (:via)',
            'consent_recorded' => 'تسجيل موافقة ولي أمر :student من الإدارة: :note',
            'consent_withdrawn' => ':guardian سحب موافقته على حساب :student',
            'consent_age_saved' => 'سن موافقة ولي الأمر بقى أقل من :age سنة',
        ],
        'classroom' => [
            'recording_settings_saved' => 'إعدادات تسجيل الحصص: :recording، :media، :days يوم',
            'recording_consent_given' => ':by وافق على تسجيل حصص :person',
            'recording_consent_recorded' => 'تسجيل موافقة :person على تسجيل الحصص من الإدارة: :note',
            'recording_consent_withdrawn' => ':by سحب موافقة تسجيل حصص :person',
            'recording_held' => 'حفظ تسجيل حصة :lesson: :reason',
            'recording_released' => 'فك حفظ تسجيل حصة :lesson',
            'recording_deleted' => 'مسح تسجيل حصة :lesson: :reason',
        ],
        'support' => [
            'complaint_validated' => 'تأكيد الشكوى :ticket على :teacher: :note',
            'complaint_dismissed' => 'رفض الشكوى :ticket: :note',
            'targets_saved' => 'تغيير مواعيد الرد على التذاكر',
        ],
        'impersonation' => [
            'started' => 'دخول بحساب :user لمدة :minutes دقيقة: :reason',
            'ended' => 'انتهى الدخول بحساب :user: :how',
        ],
        'academy' => [
            'created' => 'إنشاء الأكاديمية، والمالك :owner',
            'suspended' => 'إيقاف الأكاديمية: :reason',
            'reactivated' => 'تشغيل الأكاديمية تاني',
            'plan_changed' => 'باقة الأكاديمية بقت :plan: :reason',
        ],
        'platform' => [
            'signed_in' => 'دخول على لوحة المنصة',
            'plan_saved' => 'تعديل الباقة :plan',
        ],
    ],
    'values' => [
        'method' => ['bank_transfer' => 'تحويل بنكي', 'instapay' => 'InstaPay', 'wallet' => 'محفظة إلكترونية', 'cash' => 'كاش', 'card_terminal' => 'ماكينة كارت', 'other' => 'أخرى', 'online' => 'دفع إلكتروني'],
        'kind' => ['bonus' => 'مكافأة', 'deduction' => 'خصم'],
        'outcome' => ['completed' => 'اتعملت', 'teacher_absent' => 'غياب المدرس', 'student_absent' => 'غياب الطالب', 'cancelled_makeup' => 'اتلغت بتعويض'],
        'mode' => ['test' => 'تجريبي', 'live' => 'حقيقي'],
        'via' => ['self' => 'بنفسه', 'guardian' => 'ولي الأمر', 'family' => 'من حسابه', 'link' => 'من اللينك اللي وصله', 'staff' => 'سجلتها الإدارة', 'email' => 'أول دخول بنفس الإيميل', 'settings' => 'من إعداداته'],
        'provider' => ['google' => 'جوجل', 'apple' => 'أبل'],
        'how' => ['exit' => 'خرج بنفسه', 'expired' => 'الوقت خلص', 'ended' => 'اتقفل من لوحة المنصة'],
        'recording' => ['off' => 'مفيش تسجيل', 'manual' => 'المدرس يبدأه', 'auto' => 'كل حصة لوحدها'],
        'media' => ['by_age' => 'حسب السن', 'video' => 'فيديو', 'audio' => 'صوت بس'],
    ],
    'groups' => [
        'billing' => 'الفلوس',
        'scheduling' => 'الحصص والسياسات',
        'payroll' => 'المرتبات',
        'import' => 'الاستيراد',
        'identity' => 'الحسابات',
        'people' => 'الطلاب وأولياء الأمور',
        'classroom' => 'تسجيل الحصص',
        'support' => 'الدعم والشكاوى',
        'impersonation' => 'دخول فريق المنصة',
        'platform' => 'المنصة',
    ],
    'actor' => [
        'system' => 'النظام',
        'platform' => 'فريق المنصة · :name',
        'platform_as' => 'فريق المنصة · :name، بحساب :user',
    ],
];
