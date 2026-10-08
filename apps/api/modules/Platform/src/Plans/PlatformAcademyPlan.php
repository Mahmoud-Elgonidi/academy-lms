<?php

namespace Modules\Platform\Plans;

use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The current academy's plan, read once per request for each academy. With
 * no academy (the console, a command between academies) or an academy on no
 * plan, nothing is limited.
 */
final class PlatformAcademyPlan implements AcademyPlan
{
    /** @var array<string, ?PlanTerms> */
    private array $terms = [];

    public function __construct(private readonly TenantContext $tenants) {}

    public function terms(): ?PlanTerms
    {
        $academyId = $this->tenants->id();
        if ($academyId === null) {
            return null;
        }
        if (! array_key_exists($academyId, $this->terms)) {
            $subscription = SaasSubscription::currentFor($academyId);
            $this->terms[$academyId] = $subscription === null ? null : PlanTerms::of($subscription);
        }

        return $this->terms[$academyId];
    }

    /** After the plan changed in this request. */
    public function forget(): void
    {
        $this->terms = [];
    }

    public function has(Feature $feature): bool
    {
        return $this->terms()?->has($feature) ?? true;
    }

    public function limit(Limit $limit): ?int
    {
        return $this->terms()?->limit($limit);
    }

    public function ensureRoom(Limit $limit, int $wouldBe): void
    {
        $max = $this->limit($limit);
        if ($max !== null && $wouldBe > $max) {
            throw new PlanLimitReached($limit, $max);
        }
    }

    public function requireFeature(Feature $feature): void
    {
        if (! $this->has($feature)) {
            throw new FeatureNotInPlan($feature);
        }
    }
}
