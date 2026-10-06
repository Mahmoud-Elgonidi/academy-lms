<?php

namespace Modules\Classroom\Video\LiveKit;

use RuntimeException;

/**
 * Checks a LiveKit webhook: the Authorization header is a token signed with
 * our API secret, issued for our key, whose "sha256" claim is the hash of
 * the body. Anything else is refused.
 */
final class WebhookReceiver
{
    public function __construct(private readonly string $key, private readonly string $secret) {}

    /** @return array<string, mixed> the event */
    public function receive(string $body, ?string $authorization): array
    {
        $token = trim(preg_replace('/^Bearer\s+/i', '', (string) $authorization) ?? '');
        if ($token === '') {
            throw new RuntimeException('No signature.');
        }
        $claims = Jwt::decode($token, $this->secret);
        if (($claims['iss'] ?? null) !== $this->key) {
            throw new RuntimeException('Signed for another key.');
        }
        if (! hash_equals((string) ($claims['sha256'] ?? ''), base64_encode(hash('sha256', $body, true)))) {
            throw new RuntimeException('Body does not match its signature.');
        }

        $event = json_decode($body, true);
        if (! is_array($event)) {
            throw new RuntimeException('Not JSON.');
        }

        return $event;
    }
}
