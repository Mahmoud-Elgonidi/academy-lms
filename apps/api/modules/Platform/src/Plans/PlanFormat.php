<?php

namespace Modules\Platform\Plans;

use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/** How plans and usage read on the console and the academy's plan page. */
final class PlanFormat
{
    public static function amount(Limit $limit, ?int $value): string
    {
        if ($value === null) {
            return __('platform::plans.unlimited');
        }
        if ($limit === Limit::StorageMb) {
            return $value >= 1024
                ? __('platform::plans.units.gb', ['n' => self::number(round($value / 1024, 1))])
                : __('platform::plans.units.mb', ['n' => self::number($value)]);
        }

        return trans_choice('platform::plans.units.'.$limit->value, $value, ['n' => self::number($value)]);
    }

    /** "12 of 20", or "12" without a limit. */
    public static function usage(Limit $limit, ?int $used, ?int $max): string
    {
        if ($used === null) {
            return '—';
        }
        if ($limit === Limit::StorageMb) {
            $usedText = $used >= 1024 ? __('platform::plans.units.gb', ['n' => self::number(round($used / 1024, 1))]) : __('platform::plans.units.mb', ['n' => self::number($used)]);

            return $max === null ? $usedText : __('platform::plans.of', ['used' => $usedText, 'max' => self::amount($limit, $max)]);
        }

        return $max === null ? self::number($used) : __('platform::plans.of', ['used' => self::number($used), 'max' => self::number($max)]);
    }

    public static function price(SaasPlan $plan): ?string
    {
        return $plan->price_minor === null || $plan->currency === null ? null : Money::of($plan->price_minor, $plan->currency)->format();
    }

    /** @return array<string, string> Feature value => label, in order */
    public static function features(): array
    {
        $out = [];
        foreach (Feature::cases() as $feature) {
            $out[$feature->value] = $feature->label();
        }

        return $out;
    }

    /** 15,000 for a whole number; 2.5, or 3 for 3.0, for a fraction. */
    private static function number(int|float $n): string
    {
        return is_float($n) ? rtrim(rtrim(number_format($n, 1), '0'), '.') : number_format($n);
    }
}
