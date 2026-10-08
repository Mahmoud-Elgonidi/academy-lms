<?php

namespace Modules\Platform\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantResolver;

/**
 * Counts every active academy's usage and keeps it for the month
 * (usage_counters): the console lists academies with it, and an academy's
 * staff see the warning from it without counting on every page.
 */
final class MeasureUsage
{
    public function __construct(
        private readonly TenantResolver $academies,
        private readonly TenantContext $tenants,
        private readonly PlanUsage $usage,
    ) {}

    /** @return int how many academies were counted */
    public function all(?CarbonImmutable $at = null): int
    {
        $count = 0;
        foreach ($this->academies->active() as $academy) {
            $this->tenants->run($academy, fn () => $this->current($at));
            $count++;
        }

        return $count;
    }

    /** The current academy's counts, kept under this month. */
    public function current(?CarbonImmutable $at = null): void
    {
        $academy = $this->tenants->require();
        $key = $this->usage->period($at)->key($academy->defaultTimezone);
        $now = CarbonImmutable::now();
        $rows = [];
        foreach ($this->usage->lines($at) as $line) {
            if ($line->used !== null) {
                $rows[] = ['academy_id' => $academy->id, 'metric' => $line->limit->value, 'period' => $key, 'value' => $line->used, 'max' => $line->max, 'measured_at' => $now];
            }
        }
        DB::connection('pgsql_platform')->table('usage_counters')->upsert($rows, ['academy_id', 'metric', 'period'], ['value', 'max', 'measured_at']);
    }
}
