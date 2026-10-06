<?php

namespace Modules\Communication\Channels\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 (Android, iOS through APNs, and the web).
 * Signs in with the platform's service account (a short JWT exchanged for an
 * access token, kept for 50 minutes).
 */
final class FcmPushGateway implements PushGateway
{
    /** @param  array{project_id: ?string, credentials: ?string}  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'fcm';
    }

    public function send(array $tokens, string $title, string $body, array $data): PushResult
    {
        $project = $this->config['project_id'] ?? throw new RuntimeException('FCM is not configured.');
        $access = $this->accessToken();
        $ids = [];
        $dead = [];
        $errors = [];

        foreach ($tokens as $token) {
            $response = Http::withToken($access)->acceptJson()->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", ['message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                ]]);

            if ($response->successful()) {
                $ids[] = (string) $response->json('name');
            } elseif ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
                $dead[] = $token;
            } else {
                $errors[] = $response->json('error.message') ?? (string) $response->status();
            }
        }

        if ($ids === [] && $errors !== []) {
            throw new RuntimeException('FCM refused the message: '.$errors[0]);
        }

        return new PushResult($ids, $dead);
    }

    private function accessToken(): string
    {
        return Cache::remember('communication.fcm.token', 50 * 60, function () {
            $path = $this->config['credentials'] ?? '';
            $account = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            if (! is_array($account) || ! isset($account['client_email'], $account['private_key'])) {
                throw new RuntimeException('FCM credentials are missing or unreadable.');
            }

            $now = time();
            $segments = array_map(fn (array $part) => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '='), [
                ['alg' => 'RS256', 'typ' => 'JWT'],
                ['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600],
            ]);
            openssl_sign(implode('.', $segments), $signature, $account['private_key'], OPENSSL_ALGO_SHA256)
                || throw new RuntimeException('Could not sign the FCM token request.');
            $jwt = implode('.', $segments).'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return (string) ($response->json('access_token') ?? throw new RuntimeException('FCM sign-in failed.'));
        });
    }
}
