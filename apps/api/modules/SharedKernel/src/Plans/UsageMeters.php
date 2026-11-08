<?php

namespace Modules\SharedKernel\Plans;

use Closure;

/**
 * How much of each limit the current academy uses. The module that owns the
 * thing counts it, in its provider; where several modules hold some of it
 * (files), each adds its part and the parts are summed:
 *
 *     $meters->add(Limit::Teachers, fn (UsagePeriod $month) => ...);
 *     $meters->add(Limit::StorageMb, fn (UsagePeriod $month) => $bytes);   // storage counts bytes
 *
 * Platform reads them for the plan page, the console and the daily count; a
 * module holding a limit at its door counts with the same meter. The monthly
 * limits (video minutes, AI credits) are Platform's ledger instead; a module
 * says only what is still running (running()).
 */
final class UsageMeters
{
    /** @var array<string, list<Closure(UsagePeriod): (int|float)>> */
    private array $meters = [];

    /** @var array<string, list<Closure(): int>> */
    private array $running = [];

    /** @param  Closure(UsagePeriod): (int|float)  $count */
    public function add(Limit $limit, Closure $count): void
    {
        $this->meters[$limit->value][] = $count;
    }

    /**
     * What is in use right now and not yet counted where the limit is kept
     * (the platform's ledger): the minutes of rooms still open. Added to the
     * month's use while it lasts.
     *
     * @param  Closure(): int  $count
     */
    public function running(Limit $limit, Closure $count): void
    {
        $this->running[$limit->value][] = $count;
    }

    /** The current academy's use of the limit now, not yet counted (0 when no module says). */
    public function runningNow(Limit $limit): int
    {
        $total = 0;
        foreach ($this->running[$limit->value] ?? [] as $count) {
            $total += max(0, $count());
        }

        return $total;
    }

    public function has(Limit $limit): bool
    {
        return isset($this->meters[$limit->value]);
    }

    /** The current academy's count in the limit's unit, or null when no module counts it. */
    public function measure(Limit $limit, UsagePeriod $period): ?int
    {
        if (! isset($this->meters[$limit->value])) {
            return null;
        }
        $total = 0;
        foreach ($this->meters[$limit->value] as $count) {
            $total += $count($period);
        }

        return $limit === Limit::StorageMb ? (int) ceil($total / 1048576) : (int) $total;
    }
}
