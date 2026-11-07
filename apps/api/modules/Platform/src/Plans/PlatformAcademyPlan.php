<?php

namespace Modules\Platform\Plans;

use Modules\Platform\Models\AcademyModules;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The current academy's plan, read once per request for each academy, with
 * the optional areas the academy hid (academy_modules, docs/architecture/02,
 * "وحدات الأكاديمية"). With no academy (the console, a command between
 * academies) or an academy on no plan, nothing is limited; an academy on no
 * plan may still hide an optional area.
 */
final class PlatformAcademyPlan implements AcademyPlan
{
    /** @var array<string, ?PlanTerms> */
    private array $terms = [];

    /** @var array<string, list<string>> by academy: the optional Feature values it hid */
    private array $hidden = [];

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

    /** After the plan or the academy's modules changed in this request. */
    public function forget(): void
    {
        $this->terms = [];
        $this->hidden = [];
    }

    public function has(Feature $feature): bool
    {
        if (! $this->planHas($feature) || $this->hiddenByAcademy($feature)) {
            return false;
        }
        foreach ($feature->dependsOn() as $needed) {
            if (! $this->has($needed)) {
                return false;
            }
        }

        return true;
    }

    public function modules(): array
    {
        return array_values(array_filter(Feature::cases(), fn (Feature $f) => $this->has($f)));
    }

    /** Whether the plan (with the platform team's own changes for this academy) has the area, whatever the academy hid. */
    public function planHas(Feature $feature): bool
    {
        return $this->terms()?->has($feature) ?? true;
    }

    /** Whether the academy itself hid this optional area (Settings › Academy modules). */
    public function hiddenByAcademy(Feature $feature): bool
    {
        $academyId = $this->tenants->id();
        if ($academyId === null || ! $feature->isOptional()) {
            return false;
        }
        $this->hidden[$academyId] ??= array_values((array) AcademyModules::query()->where('academy_id', $academyId)->first()?->hidden);

        return in_array($feature->value, $this->hidden[$academyId], true);
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
            $byAcademy = $this->planHas($feature) && ($this->hiddenByAcademy($feature)
                || array_filter($feature->dependsOn(), fn (Feature $f) => $this->planHas($f) && ! $this->has($f)) !== []);
            throw new FeatureNotInPlan($feature, $byAcademy);
        }
    }
}
