<?php

use Modules\SharedKernel\Health\Faults\FcmFaults;
use Modules\SharedKernel\Health\Faults\LiveKitFaults;
use Modules\SharedKernel\Health\Faults\MetaFaults;
use Modules\SharedKernel\Health\Faults\StripeFaults;
use Modules\SharedKernel\Health\Faults\TwilioFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceProblem;

/*
 * What an outside service's error says about the service itself (docs/
 * architecture/03, section 12): the provider's code turned into our words,
 * and an answer about one recipient or one message left alone (null), so it
 * never marks the service broken nor clears a real problem.
 */

function faultOf(?ServiceFault $fault): ?string
{
    return $fault === null ? null : $fault->problem->value.'|'.$fault->code;
}

it('reads WhatsApp\'s codes', function (int $status, mixed $body, ?string $expected) {
    expect(faultOf(MetaFaults::of($status, $body)))->toBe($expected);
})->with([
    'token expired' => [401, ['error' => ['code' => 190, 'message' => 'Error validating access token']], 'key|190'],
    'no permission' => [403, ['error' => ['code' => 10]], 'permission|10'],
    'a permission code' => [403, ['error' => ['code' => 200]], 'permission|200'],
    'payment issue' => [400, ['error' => ['code' => 131042]], 'quota|131042'],
    'blocked for policy' => [400, ['error' => ['code' => 368]], 'suspended|368'],
    'account locked' => [400, ['error' => ['code' => 131031]], 'suspended|131031'],
    'rate' => [429, ['error' => ['code' => 130429]], 'rate|130429'],
    'spam rate' => [400, ['error' => ['code' => 131048]], 'rate|131048'],
    'template missing' => [404, ['error' => ['code' => 132001]], 'config|132001'],
    'number not registered' => [400, ['error' => ['code' => 133010]], 'config|133010'],
    'their side' => [500, ['error' => ['code' => 131000]], 'down|131000'],
    'one recipient: undeliverable' => [400, ['error' => ['code' => 131026]], null],
    'one recipient: outside the window' => [400, ['error' => ['code' => 131047]], null],
    'one message: bad parameter' => [400, ['error' => ['code' => 100]], null],
    'no code, refused' => [401, [], 'key|HTTP 401'],
    'no code, down' => [502, 'Bad gateway', 'down|HTTP 502'],
    'no code, a bad request' => [400, [], null],
]);

it('reads Twilio\'s codes', function (int $status, array $body, ?string $expected) {
    expect(faultOf(TwilioFaults::of($status, $body)))->toBe($expected);
})->with([
    'auth' => [401, ['code' => 20003], 'key|20003'],
    'account not active' => [401, ['code' => 20005], 'suspended|20005'],
    'too many' => [429, ['code' => 20429], 'rate|20429'],
    'region not enabled' => [400, ['code' => 21408], 'permission|21408'],
    'from not ours' => [400, ['code' => 21606], 'config|21606'],
    'trial, unverified' => [400, ['code' => 21608], 'config|21608'],
    'one recipient: bad number' => [400, ['code' => 21211], null],
    'one recipient: unsubscribed' => [400, ['code' => 21610], null],
    'no code, down' => [503, [], 'down|HTTP 503'],
    'no code, refused' => [403, [], 'permission|HTTP 403'],
]);

it('reads Firebase\'s answers', function () {
    expect(faultOf(FcmFaults::token(400, ['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'])))->toBe('key|invalid_grant')
        ->and(faultOf(FcmFaults::token(503, [])))->toBe('down|HTTP 503');

    $answer = fn (int $status, string $code) => [$status, ['error' => ['status' => 'X', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $code]]]]];
    expect(faultOf(FcmFaults::send(...$answer(404, 'UNREGISTERED'))))->toBeNull()
        ->and(faultOf(FcmFaults::send(...$answer(400, 'INVALID_ARGUMENT'))))->toBeNull()
        ->and(faultOf(FcmFaults::send(...$answer(403, 'SENDER_ID_MISMATCH'))))->toBeNull()
        ->and(faultOf(FcmFaults::send(...$answer(429, 'QUOTA_EXCEEDED'))))->toBe('rate|QUOTA_EXCEEDED')
        ->and(faultOf(FcmFaults::send(...$answer(401, 'THIRD_PARTY_AUTH_ERROR'))))->toBe('key|THIRD_PARTY_AUTH_ERROR')
        ->and(faultOf(FcmFaults::send(...$answer(503, 'UNAVAILABLE'))))->toBe('down|UNAVAILABLE')
        ->and(faultOf(FcmFaults::send(403, ['error' => ['status' => 'PERMISSION_DENIED']])))->toBe('permission|HTTP 403')
        // A project that is not the key's: every send says not found, and no device is to blame.
        ->and(faultOf(FcmFaults::send(404, ['error' => ['status' => 'NOT_FOUND']])))->toBe('config|HTTP 404')
        ->and(FcmFaults::code($answer(404, 'UNREGISTERED')[1]))->toBe('UNREGISTERED');
});

it('reads LiveKit\'s Twirp codes', function () {
    expect(faultOf(LiveKitFaults::of(401, ['code' => 'unauthenticated', 'msg' => 'invalid token'])))->toBe('key|unauthenticated')
        ->and(faultOf(LiveKitFaults::of(403, ['code' => 'permission_denied'])))->toBe('permission|permission_denied')
        ->and(faultOf(LiveKitFaults::of(429, ['code' => 'resource_exhausted'])))->toBe('rate|resource_exhausted')
        ->and(faultOf(LiveKitFaults::of(503, ['code' => 'unavailable'])))->toBe('down|unavailable')
        ->and(faultOf(LiveKitFaults::of(404, ['code' => 'not_found'])))->toBeNull()
        ->and(faultOf(LiveKitFaults::of(412, ['code' => 'failed_precondition'])))->toBeNull()
        ->and(faultOf(LiveKitFaults::of(502, 'Bad gateway')))->toBe('down|HTTP 502');
});

it('reads Stripe\'s answers on the platform\'s key, never an academy\'s account or a payer\'s card', function () {
    expect(faultOf(StripeFaults::of(401, ['error' => ['code' => 'api_key_expired', 'type' => 'invalid_request_error']])))->toBe('key|api_key_expired')
        ->and(faultOf(StripeFaults::of(403, ['error' => ['type' => 'invalid_request_error']])))->toBe('permission|invalid_request_error')
        ->and(faultOf(StripeFaults::of(403, ['error' => ['type' => 'invalid_request_error']], onConnectedAccount: true)))->toBeNull()
        ->and(faultOf(StripeFaults::of(402, ['error' => ['code' => 'card_declined']])))->toBeNull()
        ->and(faultOf(StripeFaults::of(429, [])))->toBe('rate|HTTP 429')
        ->and(faultOf(StripeFaults::of(500, [])))->toBe('down|HTTP 500');
});

it('tells the problems a person must act on from those that may pass', function () {
    expect(array_map(fn (ServiceProblem $p) => $p->value, array_values(array_filter(ServiceProblem::cases(), fn (ServiceProblem $p) => $p->severe()))))
        ->toBe(['key', 'permission', 'quota', 'suspended', 'model', 'config'])
        ->and(array_map(fn (ServiceProblem $p) => $p->value, array_values(array_filter(ServiceProblem::cases(), fn (ServiceProblem $p) => $p->transient()))))
        ->toBe(['rate', 'down', 'unreachable'])
        ->and(faultOf(ServiceFault::http(402)))->toBe('quota|HTTP 402')
        ->and(faultOf(ServiceFault::http(418)))->toBe('unknown|HTTP 418');
});
