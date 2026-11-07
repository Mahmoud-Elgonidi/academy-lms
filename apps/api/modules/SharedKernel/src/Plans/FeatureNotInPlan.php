<?php

namespace Modules\SharedKernel\Plans;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An area that is not the academy's now: a 403 "feature-not-in-plan" in the
 * API, a message on a panel screen. Either the plan leaves it out, or (an
 * optional one) the academy hid it in Settings › Academy modules.
 */
final class FeatureNotInPlan extends HttpException
{
    public function __construct(public readonly Feature $feature, public readonly bool $hiddenByAcademy = false)
    {
        parent::__construct(403, $hiddenByAcademy
            ? __('shared::plans.hidden', ['feature' => $feature->label()])
            : __('shared::plans.not_in_plan', ['feature' => $feature->label()]));
    }
}
