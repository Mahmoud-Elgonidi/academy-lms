<?php

namespace Modules\Scheduling\Domain\Policy;

use InvalidArgumentException;
use Modules\Scheduling\Domain\AttendancePolicy;

/**
 * The academy's policy numbers (docs/architecture/02, section 4, "الإعدادات
 * الافتراضية"). The rules are fixed; these numbers tune them.
 */
final readonly class PolicySettings
{
    /** key => [default, min, max] */
    public const FIELDS = [
        'student_notice_hours' => [12, 0, 168],
        'quota_per_block' => [2, 0, 20],
        'teacher_notice_hours' => [24, 0, 168],
        'late_cancel_teacher_pay_pct' => [50, 0, 100],
        'student_absent_teacher_pay_pct' => [100, 0, 100],
        'makeup_validity_days' => [30, 1, 180],
        'unpaid_cancel_hours' => [24, 0, 168],
        'teacher_late_after_min' => [2, 0, 30],
        'teacher_absent_after_min' => [10, 1, 60],
        'student_wait_short_min' => [10, 1, 60],
        'student_wait_long_min' => [15, 1, 60],
        'completion_pct' => [75, 1, 100],
    ];

    /** @param  array<string, int>  $values */
    private function __construct(private array $values) {}

    public static function defaults(): self
    {
        return new self(array_map(fn (array $f) => $f[0], self::FIELDS));
    }

    /**
     * Missing keys take their default; unknown keys are dropped.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $out = [];
        foreach (self::FIELDS as $key => [$default, $min, $max]) {
            $value = $values[$key] ?? $default;
            if (! is_numeric($value) || (int) $value != $value || $value < $min || $value > $max) {
                throw new InvalidArgumentException("Policy setting [{$key}] must be a whole number from {$min} to {$max}.");
            }
            $out[$key] = (int) $value;
        }

        return new self($out);
    }

    public function get(string $key): int
    {
        return $this->values[$key] ?? throw new InvalidArgumentException("Unknown policy setting [{$key}].");
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function attendance(): AttendancePolicy
    {
        return new AttendancePolicy(
            teacherLateAfterMin: $this->get('teacher_late_after_min'),
            teacherAbsentAfterMin: $this->get('teacher_absent_after_min'),
            studentWaitShortMin: $this->get('student_wait_short_min'),
            studentWaitLongMin: $this->get('student_wait_long_min'),
            completionShare: $this->get('completion_pct') / 100,
        );
    }
}
