<?php

namespace Modules\SharedKernel\Health\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * Stripe, on the platform's own key (Stripe Connect): what an error says
 * about that key. A card's or a payer's answer is about that payment, and an
 * academy's account that left the platform is the academy's: null.
 */
final class StripeFaults
{
    public static function of(int $status, mixed $body, bool $onConnectedAccount = false): ?ServiceFault
    {
        // The API's error.code or type; Connect's OAuth says it as a plain "error".
        $code = data_get($body, 'error.code') ?? data_get($body, 'error.type') ?? data_get($body, 'error');
        $code = is_string($code) ? $code : '';
        $problem = match (true) {
            $code === 'invalid_client', $status === 401 => ServiceProblem::Key,
            $status === 403 => $onConnectedAccount ? null : ServiceProblem::Permission,
            $status === 429 => ServiceProblem::Rate,
            $status >= 500 => ServiceProblem::Down,
            default => null,
        };

        return $problem === null ? null : new ServiceFault($problem, $code !== '' ? $code : 'HTTP '.$status);
    }
}
