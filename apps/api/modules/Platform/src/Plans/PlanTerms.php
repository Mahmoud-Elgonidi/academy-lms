<?php

namespace Modules\Platform\Plans;

use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * What an academy may do: its plan's limits and areas, with what the
 * platform team set for this academy alone over them.
 */
final readonly class PlanTerms
{
    /**
     * @param  array<string, ?int>  $limits  by Limit value; null for none
     * @param  list<string>  $overridden  the limits set for this academy alone
     * @param  list<Feature>  $features
     */
    public function __construct(
        public string $subscriptionId,
        public string $planId,
        public string $code,
        public string $nameAr,
        public string $nameEn,
        public array $limits,
        public array $overridden,
        public array $features,
    ) {}

    public static function of(SaasSubscription $subscription): self
    {
        $plan = $subscription->plan;
        $limits = [];
        $overridden = [];
        foreach (Limit::cases() as $limit) {
            $own = $subscription->limits;
            if (array_key_exists($limit->value, $own)) {
                $limits[$limit->value] = $own[$limit->value] === null ? null : (int) $own[$limit->value];
                $overridden[] = $limit->value;
            } else {
                $limits[$limit->value] = $plan->limit($limit);
            }
        }
        $on = array_values(array_unique(array_merge($plan->features, $subscription->features_on)));
        $features = array_values(array_filter(
            Feature::cases(),
            fn (Feature $f) => in_array($f->value, $on, true) && ! in_array($f->value, $subscription->features_off, true),
        ));

        return new self($subscription->id, $plan->id, $plan->code, $plan->name_ar, $plan->name_en, $limits, $overridden, $features);
    }

    public function limit(Limit $limit): ?int
    {
        return $this->limits[$limit->value] ?? null;
    }

    public function has(Feature $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->nameAr : $this->nameEn;
    }
}
