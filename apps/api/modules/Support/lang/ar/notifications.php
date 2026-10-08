<?php

return [
    'audience' => [
        'concerned' => 'اللي النوع يخصهم: الشكاوى للي بيتابع المدرسين، والدفع للي بيسجل المدفوعات، والباقي لكل اللي بيرد على التذاكر',
        'requester' => 'صاحب التذكرة',
        'assignee' => 'المسؤول عن التذكرة (أو اللي النوع يخصهم)',
        'teacher' => 'المدرس اللي الشكوى عليه',
    ],
    'ticket_opened' => ['title' => 'تذكرة جديدة :ticket: :subject', 'body' => ':requester (:kind): :excerpt'],
    'answered' => ['title' => 'الأكاديمية ردت على تذكرتك :ticket', 'body' => ':excerpt'],
    'requester_replied' => ['title' => ':requester رد على :ticket', 'body' => ':excerpt'],
    'assigned' => ['title' => 'التذكرة :ticket بقت معاك', 'body' => ':subject (:requester)'],
    'resolved' => ['title' => 'تذكرتك :ticket اتحلّت', 'body' => '":subject". لو لسه ماتحلّتش، افتحها تاني خلال :days أيام.'],
    'overdue' => ['title' => 'التذكرة :ticket مستنية رد من بدري', 'body' => ':subject (:requester): أول رد عدّى ميعاد الأكاديمية.'],
    'complaint_validated' => ['title' => 'شكوى على حصتك اتأكدت', 'body' => 'عن حصة :subject يوم :at. بتتحسب في أرقامك، وكلّم الأكاديمية لو عايز تتكلم فيها.'],
    'complaint_validated_general' => ['title' => 'شكوى على شغلك اتأكدت', 'body' => 'بتتحسب في أرقامك، وكلّم الأكاديمية لو عايز تتكلم فيها.'],
];
