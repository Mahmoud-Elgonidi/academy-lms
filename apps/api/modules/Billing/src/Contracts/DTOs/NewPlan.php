<?php

namespace Modules\Billing\Contracts\DTOs;

/**
 * A plan made from where it is missing (Find a teacher's enrolment, when no
 * plan has the lesson length and lessons a week picked): its shape, its
 * names, and a price per region as typed (region id => amount).
 */
final readonly class NewPlan
{
    /** @param  array<string, string>  $prices */
    public function __construct(
        public string $nameAr,
        public string $nameEn,
        public int $sessionMinutes,
        public int $sessionsPerWeek,
        public int $sessionsPerBlock,
        public array $prices,
        public string $lessonFormat = 'private',   // private | group
    ) {}
}
