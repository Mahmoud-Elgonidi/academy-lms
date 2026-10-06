<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** A lesson as the classroom needs it: who, when, and whether it can still be joined. */
final readonly class LessonCard
{
    public function __construct(
        public string $id,
        public string $status,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $teacherId,
        public string $teacherName,
        public ?string $studentId,
        public string $studentName,
        public string $subjectAr,
        public string $subjectEn,
        public ?string $enrollmentId,
    ) {}

    /** Scheduled or under way: not cancelled, not closed. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['scheduled', 'in_progress'], true);
    }

    public function subject(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->subjectAr : $this->subjectEn;
    }
}
