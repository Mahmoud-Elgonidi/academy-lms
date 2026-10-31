<?php

namespace Modules\Billing\Queries;

use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\SharedKernel\Money\Money;

/**
 * A group plan's price beside the private one it stands next to (the plan
 * form's hint; docs/architecture/02, "الحصص الجماعية"): per region, the
 * lesson's price on the group plan against the cheapest lesson on an active
 * private plan of the same length and lessons a week, and how much less (or
 * more) a student pays a lesson. Lessons, not blocks: the blocks may differ.
 */
final class GroupPriceComparison
{
    /**
     * @param  list<array{price_region_id?: ?string, amount?: string|int|float|null}>  $prices  as the form holds them
     * @return list<array{region: string, private: string, group_lesson: Money, private_lesson: Money, pct: int}> pct: how much less a group lesson is (negative: more)
     */
    public function handle(int $minutes, int $perWeek, int $perBlock, array $prices, ?string $exceptPlanId = null): array
    {
        if ($minutes <= 0 || $perWeek <= 0 || $perBlock <= 0) {
            return [];
        }
        $regions = PriceRegion::query()->orderBy('sort')->get()->keyBy('id');
        $private = Plan::query()->with('prices')->where('is_active', true)->where('lesson_format', Plan::PRIVATE)
            ->where('session_minutes', $minutes)->where('sessions_per_week', $perWeek)
            ->when($exceptPlanId !== null, fn ($q) => $q->whereKeyNot($exceptPlanId))->get();
        if ($private->isEmpty()) {
            return [];
        }

        $rows = [];
        foreach ($prices as $row) {
            $region = $regions[$row['price_region_id'] ?? ''] ?? null;
            $amount = $row['amount'] ?? null;
            if ($region === null || $amount === null || $amount === '' || ! is_numeric($amount)) {
                continue;
            }
            $groupLesson = intdiv(Money::parse($amount, $region->currency)->minor, $perBlock);
            $best = null;
            foreach ($private as $plan) {
                $price = $plan->prices->firstWhere('price_region_id', $region->id);
                if ($price === null || $price->currency !== $region->currency || $plan->sessions_per_block <= 0) {
                    continue;
                }
                $lesson = intdiv($price->amount_minor, $plan->sessions_per_block);
                if ($lesson > 0 && ($best === null || $lesson < $best[1])) {
                    $best = [$plan, $lesson];
                }
            }
            if ($best === null) {
                continue;
            }
            $rows[] = [
                'region' => $region->name(),
                'private' => $best[0]->name(),
                'group_lesson' => Money::of($groupLesson, $region->currency),
                'private_lesson' => Money::of($best[1], $region->currency),
                'pct' => (int) round((1 - $groupLesson / $best[1]) * 100),
            ];
        }

        return $rows;
    }
}
