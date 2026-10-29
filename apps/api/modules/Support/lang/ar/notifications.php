<?php

return [
    'audience' => [
        'concerned' => 'من يخصّهم النوع: الشكاوى لمن يتابع المعلّمين، والدفع لمن يسجّل المدفوعات، والباقي لكل من يردّ على التذاكر',
        'requester' => 'صاحب التذكرة',
        'assignee' => 'المسؤول عن التذكرة (أو من يخصّهم النوع)',
        'teacher' => 'المعلّم المشكوّ منه',
    ],
    'ticket_opened' => ['title' => 'تذكرة جديدة :ticket: :subject', 'body' => ':requester (:kind): :excerpt'],
    'answered' => ['title' => 'ردّت الأكاديمية على تذكرتك :ticket', 'body' => ':excerpt'],
    'requester_replied' => ['title' => 'ردّ :requester على :ticket', 'body' => ':excerpt'],
    'assigned' => ['title' => 'أصبحت التذكرة :ticket مسندة إليك', 'body' => ':subject (:requester)'],
    'resolved' => ['title' => 'حُلّت تذكرتك :ticket', 'body' => '":subject". إن لم تُحلّ بعد، فأعد فتحها خلال :days أيام.'],
    'overdue' => ['title' => 'التذكرة :ticket تنتظر ردًا منذ مدة', 'body' => ':subject (:requester): تجاوز أول رد الموعد الذي حدّدته الأكاديمية.'],
    'complaint_validated' => ['title' => 'تأكدت شكوى على حصتك', 'body' => 'عن حصة :subject يوم :at. تُحتسب في أرقامك، وتواصل مع الأكاديمية إن أردت مناقشتها.'],
    'complaint_validated_general' => ['title' => 'تأكدت شكوى على عملك', 'body' => 'تُحتسب في أرقامك، وتواصل مع الأكاديمية إن أردت مناقشتها.'],
];
