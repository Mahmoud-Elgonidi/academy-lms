<?php

namespace Modules\Platform\Metering;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Metering\Burn;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Domain\Metering\Thresholds;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Metering\AcademyMetering;
use Modules\SharedKernel\Plans\Events\BalanceRunningOut;
use Modules\SharedKernel\Plans\Events\LotExpiringSoon;
use Modules\SharedKernel\Plans\Events\UsageThresholdReached;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * What the staff who see the plan hear about a meter (docs/architecture/02,
 * section 11), each once (usage_alerts): 50% (unless the academy turned it
 * off), 80% and 100% of the month's allowance; bought balance ending in 30
 * days with some of it left; and, while the month's allowance is used up and
 * bought balance is in use, that it runs out in about N days at the last 14
 * days' pace. Platform only raises SharedKernel's events, inside the
 * academy's context; Communication sends the messages.
 */
final class UsageAlerts
{
    public const EXPIRING_DAYS = 30;

    /** Run-out is told when the bought balance lasts this many days or fewer. */
    public const RUNOUT_DAYS = 14;

    public function __construct(private readonly Meters $meters, private readonly TenantContext $tenants) {}

    /** After a use, and from the hourly sweep: the thresholds and the run-out. */
    public function check(string $academyId, Meter $meter): void
    {
        $state = $this->meters->state($academyId, $meter);
        if (! $state->limited || $state->allowance <= 0) {
            return;
        }
        $half = AcademyMetering::for($academyId)->half_alert;
        $raised = DB::connection('pgsql_platform')->table('usage_alerts')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('key', 'like', $state->period.':%')->pluck('key')->map(fn (string $k) => (int) substr($k, strlen($state->period) + 1))->all();
        foreach (Thresholds::reached($state->allowanceUsed(), $state->allowance, $half, $raised) as $threshold) {
            if ($this->once($academyId, $meter, $state->period.':'.$threshold)) {
                $this->raise($academyId, new UsageThresholdReached($academyId, $meter->value, $threshold, $state->period, $state->allowanceUsed(), $state->allowance, $state->bought));
            }
        }

        if ($state->allowanceLeft === 0 && $state->bought > 0) {
            $days = Burn::daysLeft($state->bought, $this->daily($academyId, $meter));
            if ($days !== null && $days <= self::RUNOUT_DAYS && $this->once($academyId, $meter, 'runout:'.$state->period)) {
                $this->raise($academyId, new BalanceRunningOut($academyId, $meter->value, $days, $state->bought));
            }
        }
    }

    /** From the hourly sweep: bought or granted balance ending within EXPIRING_DAYS with some left. */
    public function expiring(string $academyId, Meter $meter, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();
        foreach ($this->meters->state($academyId, $meter, $at)->lots as $lot) {
            if ($lot['left'] > 0 && $lot['expires_at']->lessThanOrEqualTo($at->addDays(self::EXPIRING_DAYS)) && $this->once($academyId, $meter, 'lot:'.$lot['id'])) {
                $this->raise($academyId, new LotExpiringSoon($academyId, $meter->value, $lot['id'], $lot['left'], $lot['expires_at']));
            }
        }
    }

    /**
     * The use of each of the last Burn::DAYS days, on the academy's clock.
     *
     * @return list<int>
     */
    public function daily(string $academyId, Meter $meter, ?CarbonImmutable $at = null): array
    {
        $tz = $this->meters->zone($academyId);
        $today = ($at ?? CarbonImmutable::now())->setTimezone($tz)->startOfDay();
        $from = $today->subDays(Burn::DAYS - 1);
        $rows = DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('kind', Ledger::CONSUME)->where(fn ($q) => $q->whereNull('ref_type')->orWhere('ref_type', '<>', 'overage'))
            ->where('occurred_at', '>=', $from->utc())
            ->groupByRaw('1')->selectRaw('(occurred_at at time zone ?)::date as day, -sum(amount) as used', [$tz])
            ->pluck('used', 'day');
        $out = [];
        for ($d = 0; $d < Burn::DAYS; $d++) {
            $out[] = (int) ($rows[$from->addDays($d)->toDateString()] ?? 0);
        }

        return $out;
    }

    private function once(string $academyId, Meter $meter, string $key): bool
    {
        return DB::connection('pgsql_platform')->table('usage_alerts')->insertOrIgnore([
            'academy_id' => $academyId, 'meter' => $meter->value, 'key' => mb_substr($key, 0, 80), 'raised_at' => CarbonImmutable::now(),
        ]) === 1;
    }

    /** Inside the academy's context, so whoever listens reaches its people. */
    private function raise(string $academyId, object $event): void
    {
        if ($this->tenants->id() === $academyId) {
            event($event);

            return;
        }
        $academy = Academy::query()->find($academyId);
        if ($academy !== null) {
            $this->tenants->run($academy->toTenantRef(), fn () => event($event));
        }
    }
}
