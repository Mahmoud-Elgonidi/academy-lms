<?php

// An adults academy's words (docs/architecture/02, section 29): only the keys that differ from ../teacher.php.
return [
    'week' => [
        'count' => '{0} لا توجد جلسات|{1} جلسة واحدة|{2} جلستان|[3,10] :n جلسات|[11,*] :n جلسة',
        'none' => 'لا توجد جلسات في هذه الأيام.',
    ],
    'availability' => [
        'subtitle' => 'الأوقات بتوقيتك (:zone). لا يُحجز المتدرّبون إلا ضمن هذه الأوقات.',
        'propose_hint' => 'ضع الأوقات التي تستطيع التدريب فيها كل أسبوع، وأرسلها إلى الأكاديمية لتعتمدها.',
        'from_hint' => 'إن كنت تقلّل وقتك، فيجب أن تُبلغ قبلها بـ :days يوم من أجل المتدرّبين المحجوزين.',
    ],
];
