<?php

namespace Modules\Evaluation\Domain;

/**
 * One teacher KPI as docs/architecture/02, section 7 defines it: its
 * target, which way is better, how many cases it needs before it says
 * anything, and how it is shown.
 */
final readonly class Kpi
{
    public function __construct(
        public string $key,
        public ?float $target,
        public bool $higherIsBetter,
        public int $minSample,
        public string $format,      // percent | minutes | stars
        public string $group,       // commitment | quality | professionalism | business
    ) {}

    /** Within the target? Null when there is no target or not enough cases. */
    public function meets(?float $value, int $n): ?bool
    {
        if ($value === null || $this->target === null || $n < $this->minSample) {
            return null;
        }

        return $this->higherIsBetter ? $value >= $this->target - 1e-9 : $value <= $this->target + 1e-9;
    }

    /** @return list<self> in the order the screens show them */
    public static function all(): array
    {
        return [
            new self('punctuality', 0.97, true, 5, 'percent', 'commitment'),
            new self('avg_late', null, false, 1, 'minutes', 'commitment'),
            new self('absence', 0.01, false, 5, 'percent', 'commitment'),
            new self('late_cancel', 0.02, false, 5, 'percent', 'commitment'),
            new self('taught', 0.95, true, 5, 'percent', 'commitment'),
            new self('early_leave', 0.02, false, 5, 'percent', 'commitment'),
            new self('rating', 4.6, true, 1, 'stars', 'quality'),
            new self('reports', 0.95, true, 5, 'percent', 'professionalism'),
            new self('homework', 0.90, true, 3, 'percent', 'professionalism'),
            new self('trial_conversion', 0.40, true, 1, 'percent', 'business'),
        ];
    }

    public static function find(string $key): self
    {
        foreach (self::all() as $kpi) {
            if ($kpi->key === $key) {
                return $kpi;
            }
        }

        throw new \InvalidArgumentException("Unknown KPI {$key}");
    }

    /**
     * The rating as a Bayesian average (docs/architecture/02: (C·m + Σr) / (C + n)),
     * so a handful of ratings does not swing it: C is how many ratings the
     * academy's mean m weighs as.
     */
    public static function bayesian(int $n, int $sum, float $mean, int $weight = 10): ?float
    {
        return $n === 0 ? null : ($weight * $mean + $sum) / ($weight + $n);
    }
}
