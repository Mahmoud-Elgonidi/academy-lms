<?php

namespace Modules\Platform\Ai\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * OpenAI: what an error's `error.code` (else its type) says about the
 * service. A prompt too long for the model is about that request: the
 * reason too_long rather than a fault.
 */
final class OpenAiFaults
{
    public static function of(int $status, mixed $body): ServiceFault|string
    {
        $code = (string) (data_get($body, 'error.code') ?? data_get($body, 'error.type') ?? '');
        if ($code === 'context_length_exceeded') {
            return 'too_long';
        }
        $problem = match (true) {
            in_array($code, ['insufficient_quota', 'billing_hard_limit_reached', 'billing_not_active'], true) => ServiceProblem::Quota,
            $code === 'account_deactivated' => ServiceProblem::Suspended,
            $code === 'model_not_found', $status === 404 => ServiceProblem::Model,
            $status === 400 && data_get($body, 'error.param') === 'response_format' => ServiceProblem::Model,
            $status === 401 => ServiceProblem::Key,
            $status === 403 => ServiceProblem::Permission,
            $status === 429 => ServiceProblem::Rate,
            $status >= 500 => ServiceProblem::Down,
            $status === 400 => ServiceProblem::Config,
            default => ServiceProblem::Unknown,
        };

        return new ServiceFault($problem, $code !== '' ? $code : 'HTTP '.$status);
    }
}
