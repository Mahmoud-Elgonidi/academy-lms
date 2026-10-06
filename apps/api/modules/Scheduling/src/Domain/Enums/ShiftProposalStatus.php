<?php

namespace Modules\Scheduling\Domain\Enums;

/** docs/architecture/02-domain-rules.md, section 2, "حالات المقترح". */
enum ShiftProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Countered = 'countered';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Draft, self::Submitted, self::Withdrawn],
            self::Submitted => [self::Submitted, self::Approved, self::Rejected, self::ChangesRequested, self::Countered, self::Withdrawn],
            self::ChangesRequested => [self::Submitted, self::Countered, self::Withdrawn],
            self::Countered => [self::Approved, self::Submitted, self::Countered, self::Withdrawn],
            self::Approved => [self::Superseded],
            self::Rejected, self::Withdrawn, self::Superseded => [],
        }, true);
    }

    /** Waiting on someone; at most one per teacher. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::ChangesRequested, self::Countered], true);
    }

    /** Whose move it is. */
    public function waitingOn(): ?string
    {
        return match ($this) {
            self::Submitted => 'admin',
            self::ChangesRequested, self::Countered, self::Draft => 'teacher',
            default => null,
        };
    }
}
