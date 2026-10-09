<?php

namespace Modules\Scheduling\Queries\Calendar;

use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;

/** What a calendar narrows its lessons to; anything left null (or no statuses) is everything. */
final readonly class CalendarFilter
{
    /** @var list<string> */
    public array $statuses;

    /** @param  list<string>  $statuses */
    public function __construct(
        public ?string $teacherId = null,
        public ?string $studentId = null,
        public ?string $subjectId = null,
        public ?string $type = null,
        array $statuses = [],
    ) {
        $known = array_map(fn (SessionStatus $s) => $s->value, SessionStatus::cases());
        $this->statuses = array_values(array_intersect($known, $statuses));
    }

    /** From what a screen holds: ids as given (the database answers for them), a type and statuses only when they are real. */
    public static function of(?string $teacher, ?string $student, ?string $subject, ?string $type, array $statuses): self
    {
        $id = fn (?string $v) => is_string($v) && preg_match('/^[0-9a-f-]{36}$/i', $v) ? $v : null;

        return new self($id($teacher), $id($student), $id($subject), SessionType::tryFrom((string) $type)?->value, $statuses);
    }

    /** The same lessons in every status (the status chips count each one). */
    public function anyStatus(): self
    {
        return new self($this->teacherId, $this->studentId, $this->subjectId, $this->type);
    }

    public function isEmpty(): bool
    {
        return $this->teacherId === null && $this->studentId === null && $this->subjectId === null && $this->type === null && $this->statuses === [];
    }
}
