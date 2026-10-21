<?php

namespace Modules\Platform\Ai\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * Gemini (Google AI): what an error says about the service, from its
 * ErrorInfo reason (API_KEY_INVALID, SERVICE_DISABLED, BILLING_DISABLED...),
 * its status and, for an exhausted quota, whether the quota is a day's
 * (the credit, until tomorrow or a paid plan) or a minute's (busy).
 */
final class GeminiFaults
{
    public static function of(int $status, mixed $body): ServiceFault|string
    {
        $reason = '';
        $quotas = [];
        foreach ((array) data_get($body, 'error.details', []) as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $reason = $reason !== '' ? $reason : (string) ($detail['reason'] ?? '');
            foreach ((array) ($detail['violations'] ?? []) as $violation) {
                $quotas[] = (string) (is_array($violation) ? ($violation['quotaId'] ?? '') : '');
            }
        }
        $state = (string) data_get($body, 'error.status', '');
        $daily = array_filter($quotas, fn (string $q) => stripos($q, 'PerDay') !== false) !== [];

        $problem = match (true) {
            $reason === 'API_KEY_INVALID', $state === 'UNAUTHENTICATED', $status === 401 => ServiceProblem::Key,
            $reason === 'BILLING_DISABLED' => ServiceProblem::Quota,
            $reason === 'CONSUMER_SUSPENDED' => ServiceProblem::Suspended,
            $state === 'RESOURCE_EXHAUSTED', $status === 429 => $daily ? ServiceProblem::Quota : ServiceProblem::Rate,
            $state === 'PERMISSION_DENIED', $state === 'FAILED_PRECONDITION', $status === 403 => ServiceProblem::Permission,
            $state === 'NOT_FOUND', $status === 404 => ServiceProblem::Model,
            $status >= 500 => ServiceProblem::Down,
            $status === 400 => ServiceProblem::Config,
            default => ServiceProblem::Unknown,
        };

        return new ServiceFault($problem, $reason !== '' ? $reason : ($state !== '' ? $state : 'HTTP '.$status));
    }
}
