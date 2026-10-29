<?php

// Signed in with an account a panel does not take (App\Http\Middleware\AuthenticateForPanel).
return [
    'areas' => [
        'academy' => 'لوحة الأكاديمية',
        'teach' => 'مساحة المدرّس',
        'family' => 'حساب الطالب وولي الأمر',
    ],
    'wrong' => [
        'title' => 'الحساب ده مش بيفتح :area',
        'body' => 'إنت داخل بحساب :name، وده مش مسموحله بـ:area. اخرج وادخل بالحساب الصح.',
        'sign_out' => 'اخرج وادخل بحساب تاني',
        'own' => 'روح لـ:area',
    ],
];
