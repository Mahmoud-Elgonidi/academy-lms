<?php

namespace Modules\Payroll\Domain;

use InvalidArgumentException;

/**
 * One price on a rate card: for a subject and a kind of lesson (null: any),
 * by the hour (paid by the minute) or by the lesson, in minor units.
 */
final readonly class RateLine
{
    public const UNITS = ['hour', 'lesson'];

    public function __construct(
        public ?string $subjectId,
        public ?string $sessionType,
        public string $unit,
        public int $amountMinor,
        public ?string $id = null,
    ) {
        if (! in_array($unit, self::UNITS, true) || $amountMinor < 0) {
            throw new InvalidArgumentException("Bad rate line [{$unit} {$amountMinor}].");
        }
    }

    /** How closely it fits a lesson: subject and kind 3, subject 2, kind 1, any 0; null when it does not fit. */
    public function fit(string $subjectId, string $sessionType): ?int
    {
        if (($this->subjectId !== null && $this->subjectId !== $subjectId) || ($this->sessionType !== null && $this->sessionType !== $sessionType)) {
            return null;
        }

        return ($this->subjectId !== null ? 2 : 0) + ($this->sessionType !== null ? 1 : 0);
    }
}
