<?php

namespace Modules\Announcements\Domain;

use InvalidArgumentException;

/**
 * Who an announcement is for (docs/architecture/02, section 20): every
 * family (guardians, and the students who sign in themselves), guardians
 * only, students (all, or one stage), the families of one subject's
 * courses, or the teachers. Only "students", chosen outright, reaches a
 * child the academy manages under a guardian; no audience ever reaches a
 * child still waiting for a guardian's consent (Queries\AudienceMembers).
 * An adults academy has no families, guardians or stages: there it is the
 * trainees (students, all of them) or the trainers (docs/architecture/02,
 * section 29).
 */
final readonly class Audience
{
    public const FAMILIES = 'families';

    public const GUARDIANS = 'guardians';

    public const STUDENTS = 'students';

    public const SUBJECT = 'subject';

    public const TEACHERS = 'teachers';

    public const KINDS = [self::FAMILIES, self::GUARDIANS, self::STUDENTS, self::SUBJECT, self::TEACHERS];

    /** What an adults academy may choose: every trainee, or the trainers. */
    public const ADULTS_KINDS = [self::STUDENTS, self::TEACHERS];

    /** The stages a students' announcement may keep to: People's looks, "adult" being the classic one. */
    public const STAGES = ['kids', 'middle', 'teen', 'adult'];

    private function __construct(
        public string $kind,
        public ?string $stage,
        public ?string $subjectId,
    ) {}

    /** @throws InvalidArgumentException whose message names what is wrong: kind, stage or subject */
    public static function of(string $kind, ?string $stage = null, ?string $subjectId = null): self
    {
        $stage = $stage === '' ? null : $stage;
        $subjectId = $subjectId === '' ? null : $subjectId;
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('kind');
        }
        if ($stage !== null && ($kind !== self::STUDENTS || ! in_array($stage, self::STAGES, true))) {
            throw new InvalidArgumentException('stage');
        }
        if (($kind === self::SUBJECT) !== ($subjectId !== null)) {
            throw new InvalidArgumentException('subject');
        }

        return new self($kind, $stage, $subjectId);
    }

    /** @return list<string> the kinds an academy may choose: all of them, or an adults academy's two */
    public static function kinds(bool $adultsOnly): array
    {
        return $adultsOnly ? self::ADULTS_KINDS : self::KINDS;
    }

    /** Whether an adults academy may send to it: the trainees all together, or the trainers. */
    public function fitsAdults(): bool
    {
        return in_array($this->kind, self::ADULTS_KINDS, true) && $this->stage === null;
    }

    public function forTeachers(): bool
    {
        return $this->kind === self::TEACHERS;
    }

    public function reachesGuardians(): bool
    {
        return in_array($this->kind, [self::FAMILIES, self::GUARDIANS, self::SUBJECT], true);
    }

    public function reachesStudents(): bool
    {
        return in_array($this->kind, [self::FAMILIES, self::STUDENTS, self::SUBJECT], true);
    }

    /** A child with no way in of their own (under a guardian) gets it only when students are chosen outright. */
    public function reachesManagedChildren(): bool
    {
        return $this->kind === self::STUDENTS;
    }
}
