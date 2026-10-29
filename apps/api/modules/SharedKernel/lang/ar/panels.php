<?php

// Signed in with an account a panel does not take (App\Http\Middleware\AuthenticateForPanel).
return [
    'areas' => [
        'academy' => 'لوحة الأكاديمية',
        'teach' => 'مساحة المعلّم',
        'family' => 'حساب الطالب وولي الأمر',
    ],
    'wrong' => [
        'title' => 'هذا الحساب لا يفتح :area',
        'body' => 'أنت مسجّل الدخول بحساب :name، وهذا الحساب لا يملك صلاحية :area. اخرج وادخل بالحساب الصحيح.',
        'sign_out' => 'اخرج وادخل بحساب آخر',
        'own' => 'انتقل إلى :area',
    ],
];
