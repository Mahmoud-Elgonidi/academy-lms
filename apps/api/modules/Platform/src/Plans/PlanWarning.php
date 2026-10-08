<?php

namespace Modules\Platform\Plans;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the academy's staff should hear about their plan: the limit
 * closest to full, once it is at 80% or more. Counted at most every ten
 * minutes per academy, since it shows on every page.
 */
final class PlanWarning
{
    public const MINUTES = 10;

    public function __construct(
        private readonly PlanUsage $usage,
        private readonly TenantContext $tenants,
    ) {}

    /** @return ?array{state: string, text: string} */
    public function forViewer(): ?array
    {
        if (! $this->tenants->check() || ! Gate::allows(Ability::ViewPlan->value)) {
            return null;
        }
        $worst = Cache::remember('plan-warning:'.$this->tenants->require()->id, now()->addMinutes(self::MINUTES), function (): ?array {
            $top = null;
            foreach ($this->usage->lines() as $line) {
                $ratio = $line->ratio();
                if ($ratio !== null && $ratio >= 0.8 && ($top === null || $ratio > $top['ratio'])) {
                    $top = ['limit' => $line->limit->value, 'used' => $line->used, 'max' => $line->max, 'ratio' => $ratio];
                }
            }

            return $top;
        });
        if ($worst === null) {
            return null;
        }
        $limit = Limit::from($worst['limit']);
        $state = $worst['ratio'] >= 1 ? 'full' : 'near';
        $words = [
            'what' => __('shared::plans.limits.'.$limit->value),
            'usage' => PlanFormat::usage($limit, $worst['used'], $worst['max']),
        ];

        return ['state' => $state, 'text' => __('platform::plans.banner.'.$state, $words).' '.__('platform::plans.banner.'.($limit->held() ? 'held' : 'watched').'_'.$state)];
    }

    public function forget(string $academyId): void
    {
        Cache::forget('plan-warning:'.$academyId);
    }
}
