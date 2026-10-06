<?php

namespace Modules\Communication\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A nightly window, on the person's own clock, when WhatsApp and push wait
 * ("22-07": from 22:00 to 07:00). Email and the inbox do not wait; urgent
 * messages ("join now") never do.
 */
final readonly class QuietHours
{
    public const CHOICES = ['22-07', '23-08', '21-06', 'off'];

    private function __construct(public ?int $from, public ?int $to) {}

    public static function parse(string $choice): self
    {
        if ($choice === 'off') {
            return new self(null, null);
        }
        if (! preg_match('/^(\d{1,2})-(\d{1,2})$/', $choice, $m) || (int) $m[1] > 23 || (int) $m[2] > 23 || $m[1] === $m[2]) {
            throw new InvalidArgumentException("Bad quiet hours [{$choice}].");
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    /** When a message held now may go out, or null when it may go now. */
    public function releaseAt(CarbonImmutable $now, string $timezone): ?CarbonImmutable
    {
        if ($this->from === null) {
            return null;
        }

        $local = $now->setTimezone($timezone);
        $hour = $local->hour;
        $inside = $this->from < $this->to
            ? $hour >= $this->from && $hour < $this->to
            : $hour >= $this->from || $hour < $this->to;
        if (! $inside) {
            return null;
        }

        $end = $local->setTime($this->to, 0);
        if ($end->lessThanOrEqualTo($local)) {
            $end = $end->addDay();
        }

        return $end->utc();
    }
}
