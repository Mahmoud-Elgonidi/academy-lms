<?php

return [
    'audience' => [
        'safeguarding' => 'مسؤولو حماية الأطفال في الأكاديمية',
        'teacher' => 'المعلّم',
        'teacher_and_safeguarding' => 'المعلّم ومسؤولو حماية الأطفال',
        'new_teacher' => 'المعلّم الجديد',
        'new_family' => 'ولي الأمر أو الطالب الجديد',
        'new_staff' => 'العضو الجديد في الفريق',
        'guardians' => 'أولياء أمور الطالب',
        'office' => 'من يتابعون الأسر',
    ],
    'consent_request' => ['title' => 'نحتاج إلى موافقتك على حساب :student', 'body' => 'ليدخل :student حسابه ويحضر حصصه بنفسه، نحتاج إلى موافقتك بصفتك ولي الأمر. اقرأ ما سنفعله بالتحديد ووافق من هنا: :link'],
    'consent_withdrawn' => ['title' => 'سحب :guardian موافقته على حساب :student', 'body' => 'خرج :student من حسابه، ولن يتمكن من الدخول ولا الحضور برابطه حتى يوافق ولي أمره مرة أخرى.'],
    'welcome_teacher' => ['title' => 'مرحبًا بك في :academy', 'body' => 'مرحبًا :name، حسابك معلّمًا في :academy جاهز. اختر كلمة المرور من هنا وادخل: :link'],
    'welcome_family' => ['title' => 'مرحبًا بك في :academy', 'body' => 'مرحبًا :name، حسابك في :academy جاهز: ستجد فيه الحصص والتقارير والفواتير. اختر كلمة المرور من هنا وادخل: :link'],
    'welcome_staff' => ['title' => 'مرحبًا بك في فريق :academy', 'body' => 'مرحبًا :name، أُضفت إلى فريق :academy. اختر كلمة المرور من هنا وادخل إلى لوحة الإدارة: :link'],
    'check_submitted' => ['title' => 'أرسل :teacher مستندًا للمراجعة', 'body' => 'أرسل :teacher :kind الخاص به. راجعه على الأصل، ثم أكده أو ارفضه.'],
    'check_verified' => ['title' => 'تأكّد :kind الخاص بك', 'body' => 'أكّدت الأكاديمية :kind الخاص بك، وهو صالح حتى :until.'],
    'check_rejected' => ['title' => 'يجب إرسال :kind الخاص بك مرة أخرى', 'body' => 'لم تتمكن الأكاديمية من قبول :kind الخاص بك: :reason. أرسله مرة أخرى من "مستنداتي".'],
    'check_expiring' => ['title' => 'ينتهي :kind الخاص بـ :teacher يوم :until', 'body' => 'أرسل :kind جديدًا قبل :until؛ بعدها لا تُحجز حصص جديدة مع :teacher لطلاب دون 18 إن كانت الأكاديمية تشترط الفحص.'],
    'check_expired' => ['title' => 'انتهى :kind الخاص بـ :teacher يوم :until', 'body' => 'حتى يتأكد :kind جديد، لا تُحجز حصص جديدة مع :teacher لطلاب دون 18 إن كانت الأكاديمية تشترط الفحص.'],
];
