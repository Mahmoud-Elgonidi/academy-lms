<?php

namespace Modules\SharedKernel\Health\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * WhatsApp (Meta's Cloud API): what an error's `error.code` says about the
 * service. Codes about one recipient or one message (undeliverable, outside
 * the window, a bad parameter) say nothing about the service: null.
 */
final class MetaFaults
{
    /** Template codes: the problem is that template, so it is the scope. */
    public const TEMPLATE = [132000, 132001, 132005, 132007, 132012, 132015, 132016];

    public static function of(int $status, mixed $body): ?ServiceFault
    {
        if (! is_numeric(data_get($body, 'error.code'))) {
            // No code of Meta's: by the HTTP status, only where it is the service's.
            return in_array($status, [401, 403, 429], true) || $status >= 500 ? ServiceFault::http($status) : null;
        }
        $code = (int) data_get($body, 'error.code');
        $problem = match (true) {
            in_array($code, [0, 190], true) => ServiceProblem::Key,
            in_array($code, [3, 10], true), $code >= 200 && $code <= 299 => ServiceProblem::Permission,
            in_array($code, [4, 80007, 130429, 131048], true) => ServiceProblem::Rate,
            in_array($code, [368, 131031], true) => ServiceProblem::Suspended,
            $code === 131042 => ServiceProblem::Quota,
            in_array($code, [131045, 133010], true), in_array($code, self::TEMPLATE, true) => ServiceProblem::Config,
            in_array($code, [1, 2, 131000, 131016], true) => ServiceProblem::Down,
            default => null,
        };

        // Null: one recipient or one message (131026, 131047, 131049-131051, 131056, 100, ...).
        return $problem === null ? null : new ServiceFault($problem, (string) $code);
    }
}
