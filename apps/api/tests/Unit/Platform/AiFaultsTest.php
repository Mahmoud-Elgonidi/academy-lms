<?php

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Modules\Platform\Ai\Faults\AnthropicFaults;
use Modules\Platform\Ai\Faults\ElevenLabsFaults;
use Modules\SharedKernel\Health\ServiceFault;

/*
 * Why the AI providers would not answer, from their errors: the credit that
 * ran out told apart from a wrong key, a busy moment or a retired model.
 */

function aiFault(?ServiceFault $fault): ?string
{
    return $fault === null ? null : $fault->problem->value.'|'.$fault->code;
}

function anthropicError(int $status, ?string $type, string $message = 'no'): APIStatusException
{
    $body = $type === null ? '' : json_encode(['type' => 'error', 'error' => ['type' => $type, 'message' => $message]]);

    return APIStatusException::from(new Request('POST', 'https://api.anthropic.com/v1/messages'), new Response($status, [], $body));
}

it('reads Anthropic\'s errors', function (int $status, ?string $type, string $message, string $expected) {
    expect(aiFault(AnthropicFaults::of(anthropicError($status, $type, $message))))->toBe($expected);
})->with([
    'credit balance' => [400, 'invalid_request_error', 'Your credit balance is too low to access the Anthropic API.', 'quota|invalid_request_error'],
    'billing' => [402, 'billing_error', 'billing', 'quota|billing_error'],
    'key' => [401, 'authentication_error', 'invalid x-api-key', 'key|authentication_error'],
    'permission' => [403, 'permission_error', 'no', 'permission|permission_error'],
    'model' => [404, 'not_found_error', 'model: claude-x', 'model|not_found_error'],
    'rate' => [429, 'rate_limit_error', 'slow down', 'rate|rate_limit_error'],
    'overloaded' => [529, 'overloaded_error', 'Overloaded', 'down|overloaded_error'],
    'their side' => [500, 'api_error', 'oops', 'down|api_error'],
    'another bad request' => [400, 'invalid_request_error', 'max_tokens: too many', 'config|invalid_request_error'],
    'no body' => [503, null, '', 'down|HTTP 503'],
]);

it('says Anthropic could not be reached', function () {
    expect(aiFault(AnthropicFaults::of(new APIConnectionException(new Request('POST', 'https://api.anthropic.com/v1/messages')))))->toBe('unreachable|connection')
        ->and(aiFault(AnthropicFaults::of(new RuntimeException('something else'))))->toBe('unknown|RuntimeException');
});

it('reads ElevenLabs\' errors, and leaves a recording it could not hear alone', function () {
    expect(aiFault(ElevenLabsFaults::of(401, ['detail' => ['status' => 'invalid_api_key']])))->toBe('key|invalid_api_key')
        ->and(aiFault(ElevenLabsFaults::of(401, ['detail' => ['status' => 'quota_exceeded', 'message' => 'This request exceeds your quota.']])))->toBe('quota|quota_exceeded')
        ->and(aiFault(ElevenLabsFaults::of(401, ['detail' => ['status' => 'missing_permissions']])))->toBe('permission|missing_permissions')
        ->and(aiFault(ElevenLabsFaults::of(429, ['detail' => ['status' => 'too_many_concurrent_requests']])))->toBe('rate|too_many_concurrent_requests')
        ->and(aiFault(ElevenLabsFaults::of(422, ['detail' => ['status' => 'invalid_audio']])))->toBeNull()
        ->and(aiFault(ElevenLabsFaults::of(400, [])))->toBeNull()
        ->and(aiFault(ElevenLabsFaults::of(502, 'down')))->toBe('down|HTTP 502');
});
