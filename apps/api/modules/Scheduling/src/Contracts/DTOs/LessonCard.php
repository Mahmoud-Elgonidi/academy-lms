<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A lesson as the classroom needs it: who, when, and whether it can still be
 * joined. A group lesson (groupId) has a student per seat (students, by
 * name); studentId and studentName are the first of them, as a one-to-one
 * lesson's only student. An excused student has no seat in it any more.
 */
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
        /** @var list<array{id: string, name: string, first_name: string}> every student seated */
        public array $students = [],
        public ?string $groupId = null,
        public ?int $capacity = null,
    ) {}

    public function isGroup(): bool
    {
        return $this->groupId !== null;
    }

    /** @return list<string> */
    public function studentIds(): array
    {
        return $this->students === [] ? array_values(array_filter([$this->studentId])) : array_column($this->students, 'id');
    }

    public function hasStudent(?string $userId): bool
    {
        return $userId !== null && in_array($userId, $this->studentIds(), true);
    }

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
