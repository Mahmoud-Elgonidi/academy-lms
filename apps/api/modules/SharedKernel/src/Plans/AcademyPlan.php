<?php

namespace Modules\SharedKernel\Plans;

/**
 * The current academy's SaaS plan, as any module needs it: whether an area
 * is the academy's (in the plan and, for an optional one, not hidden by the
 * academy), and how many of something it allows. Only Platform knows the
 * plans (it binds this); a module asks before it lets something in, the way
 * it asks Gate about abilities.
 *
 *     $plan->requireFeature(Feature::Payroll);
 *     $plan->ensureRoom(Limit::Teachers, $teachersNow + 1);
 */
interface AcademyPlan
{
    /** In the plan, not hidden by the academy, and what it depends on is on too. */
    public function has(Feature $feature): bool;

    /** @return list<Feature> every area that is on now (has()), in Feature's order */
    public function modules(): array;

    /** The most the plan allows, or null for no limit. */
    public function limit(Limit $limit): ?int;

    /**
     * Refuses when $wouldBe goes over the limit.
     *
     * @throws PlanLimitReached
     */
    public function ensureRoom(Limit $limit, int $wouldBe): void;

    /** @throws FeatureNotInPlan */
    public function requireFeature(Feature $feature): void;
}
