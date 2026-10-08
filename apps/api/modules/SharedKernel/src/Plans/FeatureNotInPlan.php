<?php

namespace Modules\SharedKernel\Plans;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** An area the academy's plan does not include: a 403 "feature-not-in-plan" in the API, a message on a panel screen. */
final class FeatureNotInPlan extends HttpException
{
    public function __construct(public readonly Feature $feature)
    {
        parent::__construct(403, __('shared::plans.not_in_plan', ['feature' => $feature->label()]));
    }
}
