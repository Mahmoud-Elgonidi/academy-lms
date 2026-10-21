<?php

namespace Modules\SharedKernel\Health\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * Firebase Cloud Messaging: the sign-in to Google (the service account) and
 * a message's answer. A device that is gone or a token of another app is
 * about that device: null.
 */
final class FcmFaults
{
    /** The service account's token exchange at oauth2.googleapis.com. */
    public static function token(int $status, mixed $body): ServiceFault
    {
        $error = (string) data_get($body, 'error', '');

        return in_array($error, ['invalid_grant', 'invalid_client', 'unauthorized_client'], true)
            ? new ServiceFault(ServiceProblem::Key, $error)
            : ServiceFault::http($status);
    }

    /** The FcmError code of a send's answer ("UNREGISTERED", ...), if any. */
    public static function code(mixed $body): string
    {
        foreach ((array) data_get($body, 'error.details', []) as $detail) {
            if (is_array($detail) && isset($detail['errorCode'])) {
                return (string) $detail['errorCode'];
            }
        }

        return '';
    }

    public static function send(int $status, mixed $body): ?ServiceFault
    {
        $code = self::code($body);
        $problem = match ($code) {
            'UNREGISTERED', 'INVALID_ARGUMENT', 'SENDER_ID_MISMATCH' => null,
            'QUOTA_EXCEEDED' => ServiceProblem::Rate,
            'THIRD_PARTY_AUTH_ERROR' => ServiceProblem::Key,
            'UNAVAILABLE', 'INTERNAL' => ServiceProblem::Down,
            default => match (true) {
                $status === 401 => ServiceProblem::Key,
                $status === 403 => ServiceProblem::Permission,
                // Not a device's answer: the project is not the one the key is for.
                $status === 404 => ServiceProblem::Config,
                $status === 429 => ServiceProblem::Rate,
                $status >= 500 => ServiceProblem::Down,
                default => null,
            },
        };

        return $problem === null ? null : new ServiceFault($problem, $code !== '' ? $code : 'HTTP '.$status);
    }
}
