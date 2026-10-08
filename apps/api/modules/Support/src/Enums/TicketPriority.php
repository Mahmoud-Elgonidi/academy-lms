<?php

namespace Modules\Support\Enums;

/** How soon the academy answers, by the targets in its support settings. */
enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return __('support::tickets.priorities.'.$this->value);
    }

    /** @return array{respond: int, resolve: int} hours from when the ticket came in */
    public function defaultTargets(): array
    {
        return match ($this) {
            self::Urgent => ['respond' => 1, 'resolve' => 8],
            self::High => ['respond' => 4, 'resolve' => 24],
            self::Normal => ['respond' => 24, 'resolve' => 72],
            self::Low => ['respond' => 48, 'resolve' => 168],
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Urgent => 0, self::High => 1, self::Normal => 2, self::Low => 3,
        };
    }
}
