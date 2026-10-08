<?php

namespace Modules\SharedKernel\Plans;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An API route of an area the plan may not include: `->middleware('plan.feature:crm')`.
 * Outside the plan it answers 403 "feature-not-in-plan".
 */
final class RequirePlanFeature
{
    public function __construct(private readonly AcademyPlan $plan) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $this->plan->requireFeature(Feature::from($feature));

        return $next($request);
    }
}
