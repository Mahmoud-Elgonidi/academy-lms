<?php

return [
    'audience' => [
        'safeguarding' => 'مسؤولين حماية الأطفال في الأكاديمية',
        'teacher' => 'المدرس',
        'teacher_and_safeguarding' => 'المدرس ومسؤولين حماية الأطفال',
        'new_teacher' => 'المدرس الجديد',
        'new_family' => 'ولي الأمر أو الطالب الجديد',
        'new_staff' => 'العضو الجديد في الفريق',
        'guardians' => 'أولياء أمور الطالب',
        'office' => 'اللي بيتابعوا الأسر',
    ],
    'consent_request' => ['title' => 'محتاجين موافقتك على حساب :student', 'body' => 'عشان :student يدخل حسابه ويحضر حصصه بنفسه، محتاجين موافقتك كولي أمر. اقرا هنعمل إيه بالظبط ووافق من هنا: :link'],
    'consent_withdrawn' => ['title' => ':guardian سحب موافقته على حساب :student', 'body' => ':student خرج من حسابه، ومش هيقدر يدخل ولا يحضر بلينكه لحد ما ولي أمره يوافق تاني.'],
    'welcome_teacher' => ['title' => 'أهلًا بيك في :academy', 'body' => 'أهلًا يا :name، حسابك كمدرس في :academy جاهز. اختار كلمة السر من هنا وادخل: :link'],
    'welcome_family' => ['title' => 'أهلًا بيك في :academy', 'body' => 'أهلًا يا :name، حسابك في :academy جاهز: هتلاقي فيه الحصص والتقارير والفواتير. اختار كلمة السر من هنا وادخل: :link'],
    'welcome_staff' => ['title' => 'أهلًا بيك في فريق :academy', 'body' => 'أهلًا يا :name، اتضفت لفريق :academy. اختار كلمة السر من هنا وادخل لوحة الإدارة: :link'],
    'check_submitted' => ['title' => ':teacher بعت مستند للمراجعة', 'body' => ':teacher بعت :kind بتاعه. راجعه على الأصل، وأكّده أو ارفضه.'],
    'check_verified' => ['title' => ':kind بتاعك اتأكد', 'body' => 'الأكاديمية أكدت :kind بتاعك، وصالح لحد :until.'],
    'check_rejected' => ['title' => ':kind بتاعك محتاج يتبعت تاني', 'body' => 'الأكاديمية ماقدرتش تقبل :kind بتاعك: :reason. ابعته تاني من "مستنداتي".'],
    'check_expiring' => ['title' => ':kind بتاع :teacher هيخلص يوم :until', 'body' => 'ابعت :kind جديد قبل :until؛ بعدها مفيش حصص جديدة تتحجز مع :teacher لطلاب أقل من 18 لو الأكاديمية طالبة الفحص.'],
    'check_expired' => ['title' => ':kind بتاع :teacher خلص يوم :until', 'body' => 'لحد ما :kind جديد يتأكد، مفيش حصص جديدة تتحجز مع :teacher لطلاب أقل من 18 لو الأكاديمية طالبة الفحص.'],
];
