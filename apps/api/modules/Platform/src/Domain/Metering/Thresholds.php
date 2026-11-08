<?php

namespace Modules\Platform\Domain\Metering;

/**
 * The points of the month's allowance the staff hear about: 50% (the academy
 * may turn it off), 80% and 100%; each once a month.
 */
final class Thresholds
{
    public const ALL = [50, 80, 100];

    /**
     * @param  list<int>  $raised  the thresholds already told this month
     * @return list<int> the ones reached now and not told yet
     */
    public static function reached(int $used, int $allowance, bool $halfOn, array $raised = []): array
    {
        if ($allowance <= 0) {
            return [];
        }
        $out = [];
        foreach (self::ALL as $at) {
            if (($at === 50 && ! $halfOn) || in_array($at, $raised, true)) {
                continue;
            }
            if ($used * 100 >= $allowance * $at) {
                $out[] = $at;
            }
        }

        return $out;
    }
}
