<?php

namespace Modules\SharedKernel\Plans;

use Closure;

/**
 * What holds an optional area on (docs/architecture/02, "وحدات الأكاديمية"):
 * the module that owns the area counts, in its provider, what would be left
 * hanging if the academy hid it (groups with students in running courses,
 * open tickets). Platform asks before it lets the academy hide the area and
 * says how many, and what to do first.
 *
 *     app(ModulesInUse::class)->add(Feature::Support, fn (): int => Ticket::query()->open()->count());
 */
final class ModulesInUse
{
    /** @var array<string, list<Closure(): int>> */
    private array $counts = [];

    /** @param  Closure(): int  $count  the current academy's count */
    public function add(Feature $feature, Closure $count): void
    {
        $this->counts[$feature->value][] = $count;
    }

    /** How much keeps the area on now (0: nothing). */
    public function count(Feature $feature): int
    {
        $total = 0;
        foreach ($this->counts[$feature->value] ?? [] as $count) {
            $total += $count();
        }

        return $total;
    }
}
