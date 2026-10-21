<?php

namespace Modules\Platform\Ai\Faults;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\ErrorType;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;
use Throwable;

/**
 * Anthropic (Claude): what the SDK's error says about the service, from the
 * error's type and the HTTP status. A bad request's message is read only to
 * tell its kind (BAD_REQUESTS: a credit run out, an organization without the
 * data retention a model needs, a request the model does not take, a beta not
 * enabled); it is never kept.
 */
final class AnthropicFaults
{
    /** What a 400's message is about, in order, as our own codes. */
    private const BAD_REQUESTS = [
        'data_retention' => '/data retention|zero data retention/i',
        'beta_not_enabled' => '/anthropic-beta/i',
        'model_request' => '/not supported for this model|does not support|thinking|effort|output_config|temperature|top_p|top_k|tool_choice|budget_tokens|max_tokens|prefill/i',
        'organization' => '/organization|workspace/i',
    ];

    public static function of(Throwable $e): ServiceFault
    {
        if ($e instanceof APITimeoutException) {
            return ServiceFault::unreachable('timeout');
        }
        if ($e instanceof APIConnectionException) {
            return ServiceFault::unreachable();
        }
        if (! $e instanceof APIStatusException) {
            return new ServiceFault(ServiceProblem::Unknown, class_basename($e));
        }

        return self::answer((int) $e->status, $e->type?->value, (string) data_get($e->body, 'error.message', ''));
    }

    /** The same from an answer read without the SDK (a check, the model list). */
    public static function answer(int $status, ?string $type, string $message = ''): ServiceFault
    {
        $code = $type ?? 'HTTP '.$status;
        $problem = match (true) {
            $type === ErrorType::BILLING_ERROR->value, $status === 402 => ServiceProblem::Quota,
            $status === 400 && preg_match('/credit balance|usage limit|billing/i', $message) === 1 => ServiceProblem::Quota,
            $type === ErrorType::AUTHENTICATION_ERROR->value, $status === 401 => ServiceProblem::Key,
            $type === ErrorType::PERMISSION_ERROR->value, $status === 403 => ServiceProblem::Permission,
            $type === ErrorType::NOT_FOUND_ERROR->value, $status === 404 => ServiceProblem::Model,
            $type === ErrorType::RATE_LIMIT_ERROR->value, $status === 429 => ServiceProblem::Rate,
            $type === ErrorType::OVERLOADED_ERROR->value, $type === ErrorType::API_ERROR->value, $type === ErrorType::TIMEOUT_ERROR->value, $status >= 500 => ServiceProblem::Down,
            $status === 400 => ServiceProblem::Config,
            default => ServiceProblem::Unknown,
        };

        // A credit run out comes as a plain bad request: its code says which.
        if ($problem === ServiceProblem::Quota && $type !== ErrorType::BILLING_ERROR->value) {
            return new ServiceFault($problem, 'credit_balance');
        }
        if ($problem === ServiceProblem::Config) {
            foreach (self::BAD_REQUESTS as $kind => $pattern) {
                if (preg_match($pattern, $message) === 1) {
                    return new ServiceFault($problem, $kind);
                }
            }
        }

        return new ServiceFault($problem, $code);
    }
}
