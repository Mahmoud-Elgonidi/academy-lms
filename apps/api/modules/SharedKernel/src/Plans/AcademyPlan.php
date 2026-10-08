<?php

namespace Modules\SharedKernel\Plans;

/**
 * The current academy's SaaS plan, as any module needs it: whether an area
 * is in the plan, and how many of something it allows. Only Platform knows
 * the plans (it binds this); a module asks before it lets something in, the
 * way it asks Gate about abilities.
 *
 *     $plan->requireFeature(Feature::Payroll);
 *     $plan->ensureRoom(Limit::Teachers, $teachersNow + 1);
 */
interface AcademyPlan
{
    public function has(Feature $feature): bool;

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
