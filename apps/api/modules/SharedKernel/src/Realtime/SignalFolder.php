<?php

namespace Modules\SharedKernel\Realtime;

/**
 * Folds a burst of changes into one signal per key ("board:{academy}",
 * "inbox:{user}"...): the first change waits a quarter of a second for the
 * ones right behind it, and a key gets at most one signal a second however
 * busy it is. Pure: times are milliseconds passed in.
 */
final class SignalFolder
{
    public const GATHER_MS = 250;

    public const MIN_GAP_MS = 1000;

    /** @var array<string, float> key => when its signal goes out */
    private array $due = [];

    /** @var array<string, float> key => when it last went out */
    private array $sent = [];

    public function changed(string $key, float $nowMs): void
    {
        $this->due[$key] ??= max($nowMs + self::GATHER_MS, ($this->sent[$key] ?? -INF) + self::MIN_GAP_MS);
    }

    /** @return list<string> keys whose signal is due now; they count as sent */
    public function take(float $nowMs): array
    {
        $ready = array_keys(array_filter($this->due, fn (float $at) => $at <= $nowMs));
        foreach ($ready as $key) {
            unset($this->due[$key]);
            $this->sent[$key] = $nowMs;
        }

        return array_map('strval', $ready);
    }

    /** Milliseconds until the next signal is due, or null when none is waiting. */
    public function waitMs(float $nowMs): ?int
    {
        return $this->due === [] ? null : (int) max(0, ceil(min($this->due) - $nowMs));
    }
}
