<?php

namespace Modules\SharedKernel\Health;

/**
 * What a provider's answer says went wrong with the service itself: the
 * problem in our words and the provider's own code (never its message, which
 * may carry a key or someone's data).
 */
final readonly class ServiceFault
{
    public function __construct(
        public ServiceProblem $problem,
        public string $code = '',
    ) {}

    /** When a provider's body says nothing we know: by the HTTP status alone. */
    public static function http(int $status): self
    {
        $problem = match (true) {
            $status === 401 => ServiceProblem::Key,
            $status === 402 => ServiceProblem::Quota,
            $status === 403 => ServiceProblem::Permission,
            $status === 404 => ServiceProblem::Config,
            $status === 429 => ServiceProblem::Rate,
            $status === 408, $status >= 500 => ServiceProblem::Down,
            default => ServiceProblem::Unknown,
        };

        return new self($problem, 'HTTP '.$status);
    }

    public static function unreachable(string $code = 'connection'): self
    {
        return new self(ServiceProblem::Unreachable, $code);
    }
}
