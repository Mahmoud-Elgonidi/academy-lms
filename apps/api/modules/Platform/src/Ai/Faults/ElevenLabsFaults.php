<?php

namespace Modules\Platform\Ai\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * ElevenLabs (speech to text): what an error's `detail.status` says about the
 * service, whatever the HTTP status. A recording it could not hear (400, 422)
 * is about that recording: null.
 */
final class ElevenLabsFaults
{
    public static function of(int $status, mixed $body): ?ServiceFault
    {
        $code = (string) data_get($body, 'detail.status', '');
        $problem = match ($code) {
            'invalid_api_key', 'api_key_not_found' => ServiceProblem::Key,
            'quota_exceeded', 'insufficient_credits', 'payment_required' => ServiceProblem::Quota,
            'missing_permissions' => ServiceProblem::Permission,
            'too_many_concurrent_requests', 'rate_limit_exceeded' => ServiceProblem::Rate,
            'system_busy', 'service_unavailable' => ServiceProblem::Down,
            default => match (true) {
                in_array($status, [400, 422], true) => null,
                default => ServiceFault::http($status)->problem,
            },
        };

        return $problem === null ? null : new ServiceFault($problem, $code !== '' ? $code : 'HTTP '.$status);
    }
}
