<?php

namespace Modules\Platform\Metering;

use Carbon\CarbonImmutable;
use Modules\Platform\Recharging\RechargeSweep;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantResolver;
use Throwable;

/**
 * Every hour, for each active academy and meter: the month's allowance comes
 * in on the first on its clock, lots that ended are written off (expire),
 * holds nobody settled let go, and the alerts are checked (thresholds,
 * run-out, balance ending in 30 days); then the recharges (Checkout pages
 * left open asked about, automatic recharge: Recharging\RechargeSweep). Each
 * academy on its own: one that fails is reported and the others go on.
 */
final class MeteringSweep
{
    public function __construct(
        private readonly TenantResolver $academies,
        private readonly TenantContext $tenants,
        private readonly Meters $meters,
        private readonly UsageAlerts $alerts,
    ) {}

    /** @return int how many academies were seen to */
    public function all(?CarbonImmutable $at = null): int
    {
        $count = 0;
        foreach ($this->academies->active() as $academy) {
            try {
                $this->tenants->run($academy, fn () => $this->academy($academy->id, $at));
                $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    public function academy(string $academyId, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();
        foreach (Meter::cases() as $meter) {
            $this->meters->ensureAllowance($academyId, $meter, $at);
            $this->meters->expireLots($academyId, $meter, $at);
            $this->meters->releaseStale($academyId, $meter, $at);
            $this->alerts->check($academyId, $meter);
            $this->alerts->expiring($academyId, $meter, $at);
        }
        app(RechargeSweep::class)->academy($academyId, $at);
    }
}
