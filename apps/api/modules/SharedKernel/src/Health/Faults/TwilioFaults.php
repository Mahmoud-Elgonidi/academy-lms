<?php

namespace Modules\SharedKernel\Health\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * Twilio: what an error's `code` says about the service. A bad, unsubscribed
 * or unreachable number is about one recipient: null.
 */
final class TwilioFaults
{
    public static function of(int $status, mixed $body): ?ServiceFault
    {
        $code = (int) data_get($body, 'code', 0);
        $problem = match ($code) {
            20003 => ServiceProblem::Key,
            20005 => ServiceProblem::Suspended,
            20429 => ServiceProblem::Rate,
            21408 => ServiceProblem::Permission,
            20008, 21212, 21606, 21608, 21659, 21660 => ServiceProblem::Config,
            default => null,
        };
        if ($problem !== null) {
            return new ServiceFault($problem, (string) $code);
        }

        return $code !== 0 || ! in_array($status, [401, 403, 429], true) && $status < 500 ? null : ServiceFault::http($status);
    }
}
