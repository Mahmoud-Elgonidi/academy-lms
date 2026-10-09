<?php

namespace Modules\Billing\Domain\Revenue;

use InvalidArgumentException;

/**
 * Shares an amount in proportion to weights, in whole minor units, so the
 * parts always add up to the amount (largest remainder; ties to the first
 * key). Used to say which course a payment on a family invoice was for.
 */
final class Split
{
    /**
     * @template K of array-key
     *
     * @param  array<K, int>  $weights  not negative; all zero gives everything to the first
     * @return array<K, int>
     */
    public static function byWeight(int $amount, array $weights): array
    {
        if ($weights === []) {
            throw new InvalidArgumentException('Nothing to share between.');
        }
        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('Weights are not negative.');
            }
        }
        $total = array_sum($weights);
        if ($total === 0) {
            $parts = array_map(fn () => 0, $weights);
            $parts[array_key_first($weights)] = $amount;

            return $parts;
        }

        $parts = [];
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $exact = intdiv($amount * $weight, $total);
            $parts[$key] = $exact;
            $remainders[$key] = ($amount * $weight) % $total;
        }
        $left = $amount - array_sum($parts);
        // Largest remainders first; equal ones in the order given.
        $position = array_flip(array_keys($weights));
        $order = array_keys($remainders);
        usort($order, fn ($a, $b) => [$remainders[$b], $position[$a]] <=> [$remainders[$a], $position[$b]]);
        foreach (array_slice($order, 0, $left) as $key) {
            $parts[$key]++;
        }

        return $parts;
    }
}
