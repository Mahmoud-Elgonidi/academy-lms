<?php

namespace Modules\Scheduling\Live;

/**
 * Folds a burst of changes into one signal per academy: the first change
 * waits a quarter of a second for the ones right behind it, and an academy
 * gets at most one signal a second however busy it is. Pure: times are
 * milliseconds passed in.
 */
final class BoardSignals
{
    public const GATHER_MS = 250;

    public const MIN_GAP_MS = 1000;

    /** @var array<string, float> academy => when its signal goes out */
    private array $due = [];

    /** @var array<string, float> academy => when it last went out */
    private array $sent = [];

    public function changed(string $academyId, float $nowMs): void
    {
        $this->due[$academyId] ??= max($nowMs + self::GATHER_MS, ($this->sent[$academyId] ?? -INF) + self::MIN_GAP_MS);
    }

    /** @return list<string> academies whose signal is due now; they count as sent */
    public function take(float $nowMs): array
    {
        $ready = array_keys(array_filter($this->due, fn (float $at) => $at <= $nowMs));
        foreach ($ready as $academyId) {
            unset($this->due[$academyId]);
            $this->sent[$academyId] = $nowMs;
        }

        return array_map('strval', $ready);
    }

    /** Milliseconds until the next signal is due, or null when none is waiting. */
    public function waitMs(float $nowMs): ?int
    {
        return $this->due === [] ? null : (int) max(0, ceil(min($this->due) - $nowMs));
    }
}
