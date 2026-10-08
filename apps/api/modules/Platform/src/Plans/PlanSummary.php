<?php

namespace Modules\Platform\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsagePeriod;

/**
 * An academy's plan as its screens show it: each limit with its use (a
 * meter), the areas in and out, and the plans it was on. The console reads
 * the day's counts; the academy's own page counts now.
 */
final class PlanSummary
{
    /**
     * @param  list<UsageLine>  $lines
     * @return array{name: string, code: string, meters: list<array<string, mixed>>, features: list<array{label: string, on: bool, own: bool}>, measured: ?CarbonImmutable}
     */
    public static function of(PlanTerms $terms, array $lines, ?CarbonImmutable $measured = null, ?SaasSubscription $subscription = null): array
    {
        $meters = [];
        foreach ($lines as $line) {
            $ratio = $line->ratio();
            $meters[] = [
                'key' => $line->limit->value,
                'label' => __('shared::plans.limits.'.$line->limit->value),
                'text' => PlanFormat::usage($line->limit, $line->used, $line->max),
                'max' => PlanFormat::amount($line->limit, $line->max),
                'percent' => $ratio === null ? null : (int) min(100, round($ratio * 100)),
                'state' => $line->state(),
                'own' => $line->overridden,
                'monthly' => $line->limit->monthly(),
                'unlimited' => $line->max === null,
                'counted' => $line->used !== null,
            ];
        }
        $features = array_map(fn (Feature $f) => [
            'label' => $f->label(),
            'on' => $terms->has($f),
            'own' => $subscription !== null && (in_array($f->value, $subscription->features_on, true) || in_array($f->value, $subscription->features_off, true)),
        ], Feature::cases());

        return ['name' => $terms->name(), 'code' => $terms->code, 'meters' => $meters, 'features' => $features, 'measured' => $measured];
    }

    /**
     * The academy's last counts this month (usage_counters), as usage lines.
     *
     * @return array{lines: list<UsageLine>, measured: ?CarbonImmutable}
     */
    public static function counted(string $academyId, string $timezone, PlanTerms $terms): array
    {
        $period = UsagePeriod::monthOf(CarbonImmutable::now(), $timezone)->key($timezone);
        $rows = DB::connection('pgsql_platform')->table('usage_counters')->where('academy_id', $academyId)->where('period', $period)->get()->keyBy('metric');
        $lines = array_map(fn (Limit $l) => new UsageLine(
            $l,
            isset($rows[$l->value]) ? (int) $rows[$l->value]->value : null,
            $terms->limit($l),
            in_array($l->value, $terms->overridden, true),
        ), Limit::cases());
        $measured = $rows->max('measured_at');

        return ['lines' => $lines, 'measured' => $measured ? CarbonImmutable::parse($measured) : null];
    }

    /** @return list<array{name: string, from: CarbonImmutable, to: ?CarbonImmutable, reason: ?string}> the plans it was on, newest first */
    public static function history(string $academyId): array
    {
        return SaasSubscription::query()->with('plan')->where('academy_id', $academyId)->orderByDesc('started_at')->limit(12)->get()
            ->map(fn (SaasSubscription $s) => ['name' => $s->plan->name(), 'from' => $s->started_at, 'to' => $s->ended_at, 'reason' => $s->reason])->all();
    }
}
