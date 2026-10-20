<?php

namespace Modules\Platform\Ai\Faults;

use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * Ollama (the platform's own server): a model it has not pulled, a proxy in
 * front that refused the key, or the server itself in trouble.
 */
final class OllamaFaults
{
    public static function of(int $status, mixed $body): ServiceFault|string
    {
        $problem = match (true) {
            $status === 404 => ServiceProblem::Model,
            in_array($status, [401, 403], true) => ServiceProblem::Key,
            $status === 429 => ServiceProblem::Rate,
            $status >= 500 => ServiceProblem::Down,
            $status === 400 => ServiceProblem::Config,
            default => ServiceProblem::Unknown,
        };

        return new ServiceFault($problem, 'HTTP '.$status);
    }
}
