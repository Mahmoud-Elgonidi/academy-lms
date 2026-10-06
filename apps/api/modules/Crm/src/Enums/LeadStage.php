<?php

namespace Modules\Crm\Enums;

/** Where a lead stands (docs/architecture/01, section 14): from first contact to a subscription or a loss. */
enum LeadStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case TrialBooked = 'trial_booked';
    case Attended = 'attended';
    case Assessed = 'assessed';
    case Won = 'won';
    case Lost = 'lost';

    /** The board's columns, in order. */
    public const OPEN = [self::New, self::Contacted, self::TrialBooked, self::Attended, self::Assessed];

    public function isOpen(): bool
    {
        return in_array($this, self::OPEN, true);
    }

    /** Its place on the way to a subscription (0 to 5). */
    public function step(): int
    {
        return match ($this) {
            self::New, self::Lost => 0,
            self::Contacted => 1,
            self::TrialBooked => 2,
            self::Attended => 3,
            self::Assessed => 4,
            self::Won => 5,
        };
    }
}
