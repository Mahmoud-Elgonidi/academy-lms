<?php

namespace Modules\SharedKernel\Health\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * LiveKit's server API (Twirp): what an error's `code` says about the
 * service. A room or a recording that is not there is about that one: null.
 */
final class LiveKitFaults
{
    public static function of(int $status, mixed $body): ?ServiceFault
    {
        $code = (string) data_get($body, 'code', '');
        $problem = match ($code) {
            'unauthenticated' => ServiceProblem::Key,
            'permission_denied' => ServiceProblem::Permission,
            'resource_exhausted' => ServiceProblem::Rate,
            'unavailable', 'internal', 'deadline_exceeded', 'unknown', 'dataloss' => ServiceProblem::Down,
            'not_found', 'already_exists', 'failed_precondition', 'invalid_argument', 'out_of_range', 'aborted', 'canceled', 'malformed', 'bad_route' => null,
            default => match (true) {
                $status === 401 => ServiceProblem::Key,
                $status === 403 => ServiceProblem::Permission,
                $status === 429 => ServiceProblem::Rate,
                $status >= 500 => ServiceProblem::Down,
                default => null,
            },
        };

        return $problem === null ? null : new ServiceFault($problem, $code !== '' ? $code : 'HTTP '.$status);
    }
}
