<?php

namespace Modules\SharedKernel\Plans;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Something new would go over the academy's plan. A validation error (so an
 * import row or a form says why), a 403 "plan-limit-reached" in the API, and
 * a message on any panel screen.
 */
final class PlanLimitReached extends ValidationException
{
    public function __construct(public readonly Limit $limit, public readonly int $max)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('plan', __('shared::plans.reached.'.$limit->value, ['max' => $max]));
        parent::__construct($validator);
    }
}
