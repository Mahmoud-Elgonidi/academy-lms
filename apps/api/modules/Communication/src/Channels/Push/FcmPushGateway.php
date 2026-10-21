<?php

namespace Modules\Communication\Channels\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\SharedKernel\Health\Faults\FcmFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 (Android, iOS through APNs, and the web).
 * Signs in with the platform's service account (a short JWT exchanged for an
 * access token, kept for 50 minutes per account): the file an owner uploaded
 * in the console, else the one at FCM_CREDENTIALS on the server. A device
 * is gone only when FCM says UNREGISTERED; how FCM answers otherwise goes to
 * ServiceHealth ('fcm'): a refused service account, a project that is not
 * the key's, a quota, trouble on its side.
 */
final class FcmPushGateway implements PushGateway
{
    /** @param  array{project_id: ?string, credentials: ?string, credentials_json?: ?string}  $config */
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

        $fault = null;

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($access)->acceptJson()->timeout(10)
                    ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", ['message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                    ]]);
            } catch (ConnectionException $e) {
                ServiceHealth::fault('fcm', ServiceFault::unreachable());
                throw $e;
            }

            if ($response->successful()) {
                $ids[] = (string) $response->json('name');
            } elseif (FcmFaults::code($response->json()) === 'UNREGISTERED') {
                $dead[] = $token;
            } else {
                $fault ??= FcmFaults::send($response->status(), $response->json());
                $errors[] = $response->json('error.message') ?? (string) $response->status();
            }
        }

        if ($ids !== []) {
            ServiceHealth::ok('fcm');
        } elseif ($fault !== null) {
            ServiceHealth::fault('fcm', $fault);
        }
        if ($ids === [] && $errors !== []) {
            throw new RuntimeException('FCM refused the message: '.$errors[0]);
        }

        return new PushResult($ids, $dead);
    }

    private function accessToken(): string
    {
        $json = (string) ($this->config['credentials_json'] ?? '');
        $path = (string) ($this->config['credentials'] ?? '');
        if ($json === '' && is_file($path)) {
            $json = (string) file_get_contents($path);
        }
        $account = json_decode($json, true);
        if (! is_array($account) || ! isset($account['client_email'], $account['private_key'])) {
            throw new RuntimeException('FCM credentials are missing or unreadable.');
        }

        // Per account and key, so a new file is signed in with at once.
        return Cache::remember('communication.fcm.token.'.sha1($account['client_email'].'|'.($account['private_key_id'] ?? '')), 50 * 60, function () use ($account) {

            $now = time();
            $segments = array_map(fn (array $part) => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '='), [
                ['alg' => 'RS256', 'typ' => 'JWT'],
                ['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600],
            ]);
            openssl_sign(implode('.', $segments), $signature, $account['private_key'], OPENSSL_ALGO_SHA256)
                || throw new RuntimeException('Could not sign the FCM token request.');
            $jwt = implode('.', $segments).'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            try {
                $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
            } catch (ConnectionException $e) {
                ServiceHealth::fault('fcm', ServiceFault::unreachable());
                throw $e;
            }
            if (! filled($response->json('access_token'))) {
                ServiceHealth::fault('fcm', FcmFaults::token($response->status(), $response->json()));
                throw new RuntimeException('FCM sign-in failed.');
            }

            return (string) $response->json('access_token');
        });
    }
}
