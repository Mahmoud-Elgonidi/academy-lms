<?php

namespace Modules\Scheduling\Domain\Disputes;

use Carbon\CarbonImmutable;

/**
 * Who may dispute a lesson's outcome, about what, and until when
 * (docs/architecture/01, section 8): a decided lesson (held, or someone
 * absent), within 48 hours of its end, once. The family says what went
 * wrong on its side, the teacher on theirs.
 */
final class DisputeRules
{
    public const HOURS = 48;

    /** The outcomes a dispute can be about: the lesson was held, or someone was absent. */
    public const OUTCOMES = ['completed', 'student_absent', 'teacher_absent'];

    /** @var array<string, list<string>> */
    public const REASONS = [
        'family' => ['teacher_absent', 'student_attended', 'cut_short', 'technical', 'other'],
        'teacher' => ['teacher_attended', 'student_absent', 'technical', 'other'],
    ];

    public const SIDES = ['family', 'teacher'];

    /** Reasons that only fit some outcomes (the student was there: only on "student absent"); the rest fit any. */
    private const ABOUT = [
        'teacher_absent' => ['completed'],
        'cut_short' => ['completed'],
        'student_attended' => ['student_absent'],
        'teacher_attended' => ['teacher_absent'],
        'student_absent' => ['completed'],
    ];

    /** Whether a lesson in this state may still be disputed now. */
    public static function open(string $status, CarbonImmutable $endsAt, CarbonImmutable $now): bool
    {
        return in_array($status, self::OUTCOMES, true) && $now->lessThanOrEqualTo(self::closesAt($endsAt));
    }

    public static function closesAt(CarbonImmutable $endsAt): CarbonImmutable
    {
        return $endsAt->addHours(self::HOURS);
    }

    /** @return list<string> the reasons a side may give about a lesson with this outcome */
    public static function reasons(string $side, string $status): array
    {
        return array_values(array_filter(self::REASONS[$side] ?? [], fn (string $r) => ! isset(self::ABOUT[$r]) || in_array($status, self::ABOUT[$r], true)));
    }

    public static function reasonFits(string $side, string $reason, string $status): bool
    {
        return in_array($reason, self::reasons($side, $status), true);
    }
}
