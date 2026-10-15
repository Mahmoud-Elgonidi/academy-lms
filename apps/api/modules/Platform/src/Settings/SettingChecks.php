<?php

namespace Modules\Platform\Settings;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Health\TestMail;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\TestAddresses;
use Throwable;

/**
 * "Try it" on the Platform settings screen: one request to each service
 * with the settings in force (the screen's, else the server's), that
 * changes nothing there (an email to the owner who asked aside). It says
 * whether the service took the keys, in our words: never the provider's,
 * never a key.
 */
final class SettingChecks
{
    /** The sections that have a try. */
    public const GROUPS = ['ai', 'mail', 'whatsapp', 'sms', 'push', 'classroom', 'stripe'];

    private const TIMEOUT = 10;

    /** @return array{ok: bool, says: string} */
    public function run(string $group, string $ownerEmail): array
    {
        try {
            return match ($group) {
                'ai' => $this->ai(),
                'mail' => $this->mail($ownerEmail),
                'whatsapp' => $this->whatsapp(),
                'sms' => $this->sms(),
                'push' => $this->push(),
                'classroom' => $this->classroom(),
                'stripe' => $this->stripe(),
                default => self::no('none'),
            };
        } catch (ConnectionException) {
            return self::no('unreachable');
        }
    }

    /** @return array{ok: bool, says: string} */
    private function ai(): array
    {
        $key = (string) config('platform.ai.key');
        if ($key === '') {
            return self::no('missing');
        }
        $base = rtrim((string) (config('platform.ai.base_url') ?: 'https://api.anthropic.com'), '/');
        foreach (array_unique(array_filter([config('platform.ai.models.best'), config('platform.ai.models.fast'), config('platform.games.designer.model')])) as $model) {
            $answer = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->acceptJson()->timeout(self::TIMEOUT)
                ->get($base.'/v1/models/'.rawurlencode((string) $model));
            if ($answer->status() === 404) {
                return self::no('model_missing', ['model' => (string) $model]);
            }
            if (! $answer->successful()) {
                return self::answer($answer);
            }
        }

        $speech = (string) config('platform.ai.speech.key');
        if ($speech === '') {
            return self::yes('ai_no_speech');
        }
        $heard = Http::withHeaders(['xi-api-key' => $speech])->acceptJson()->timeout(self::TIMEOUT)
            ->get(rtrim((string) config('platform.ai.speech.base_url'), '/').'/v1/user');
        // A key limited to speech to text may not read the account: taken all the same.
        if ($heard->successful() || $heard->json('detail.status') === 'missing_permissions') {
            return self::yes('ai');
        }

        return self::no('speech_refused');
    }

    /** @return array{ok: bool, says: string} */
    private function mail(string $email): array
    {
        if (config('mail.default') !== 'smtp') {
            return self::no('mail_log');
        }
        // A demo address never reaches a mail server (TestAddresses): saying "sent" would mislead.
        if (TestAddresses::skips($email)) {
            return self::no('mail_demo', ['email' => $email]);
        }
        try {
            Mail::to($email)->send(new TestMail);
        } catch (Throwable $e) {
            MailHealth::failed($e);

            return self::no('mail_failed');
        }

        return self::yes('mail', ['email' => $email]);
    }

    /** @return array{ok: bool, says: string} */
    private function whatsapp(): array
    {
        $meta = (array) config('communication.whatsapp.meta');
        if (blank($meta['token'] ?? null) || blank($meta['phone_number_id'] ?? null)) {
            return self::no('missing');
        }
        $answer = Http::withToken((string) $meta['token'])->acceptJson()->timeout(self::TIMEOUT)
            ->get('https://graph.facebook.com/'.($meta['api_version'] ?? 'v21.0').'/'.rawurlencode((string) $meta['phone_number_id']), ['fields' => 'display_phone_number']);

        return $answer->successful() ? self::yes('whatsapp', ['number' => (string) $answer->json('display_phone_number')]) : self::answer($answer);
    }

    /** @return array{ok: bool, says: string} */
    private function sms(): array
    {
        $twilio = (array) config('communication.sms.twilio');
        if (blank($twilio['sid'] ?? null) || blank($twilio['token'] ?? null)) {
            return self::no('missing');
        }
        $answer = Http::withBasicAuth((string) $twilio['sid'], (string) $twilio['token'])->acceptJson()->timeout(self::TIMEOUT)
            ->get('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode((string) $twilio['sid']).'.json');

        return $answer->successful() ? self::yes('sms') : self::answer($answer);
    }

    /** @return array{ok: bool, says: string} */
    private function push(): array
    {
        $fcm = (array) config('communication.push.fcm');
        $json = (string) ($fcm['credentials_json'] ?? '');
        if ($json === '' && is_file((string) ($fcm['credentials'] ?? ''))) {
            $json = (string) file_get_contents((string) $fcm['credentials']);
        }
        $account = json_decode($json, true);
        if (blank($fcm['project_id'] ?? null) || ! is_array($account) || ! isset($account['client_email'], $account['private_key'])) {
            return self::no('missing');
        }
        $now = time();
        $segments = array_map(fn (array $part) => self::base64url((string) json_encode($part)), [
            ['alg' => 'RS256', 'typ' => 'JWT'],
            ['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 300],
        ]);
        if (! @openssl_sign(implode('.', $segments), $signature, (string) $account['private_key'], OPENSSL_ALGO_SHA256)) {
            return self::no('push_key');
        }
        $answer = Http::asForm()->timeout(self::TIMEOUT)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => implode('.', $segments).'.'.self::base64url($signature),
        ]);

        return $answer->successful() && filled($answer->json('access_token')) ? self::yes('push') : self::answer($answer);
    }

    /** @return array{ok: bool, says: string} */
    private function classroom(): array
    {
        $livekit = (array) config('classroom.livekit');
        if (blank($livekit['key'] ?? null) || blank($livekit['secret'] ?? null) || blank($livekit['api_url'] ?? null)) {
            return self::no('missing');
        }
        // The room service's own sign-in: a short token for listing rooms, signed with the server's secret.
        $now = time();
        $unsigned = self::base64url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])).'.'
            .self::base64url((string) json_encode(['iss' => $livekit['key'], 'sub' => 'platform-settings', 'nbf' => $now - 10, 'exp' => $now + 120, 'video' => ['roomList' => true]]));
        $jwt = $unsigned.'.'.self::base64url(hash_hmac('sha256', $unsigned, (string) $livekit['secret'], true));
        $answer = Http::withToken($jwt)->acceptJson()->timeout(self::TIMEOUT)
            ->post(rtrim((string) $livekit['api_url'], '/').'/twirp/livekit.RoomService/ListRooms', (object) []);

        return $answer->successful() ? self::yes('classroom') : self::answer($answer);
    }

    /** @return array{ok: bool, says: string} */
    private function stripe(): array
    {
        $secret = (string) config('billing.payments.stripe.platform_secret');
        if ($secret === '') {
            return self::no('missing');
        }
        $answer = Http::withBasicAuth($secret, '')->acceptJson()->timeout(self::TIMEOUT)
            ->get(rtrim((string) config('billing.payments.stripe.base', 'https://api.stripe.com'), '/').'/v1/account');

        return $answer->successful() ? self::yes('stripe') : self::answer($answer);
    }

    /** @return array{ok: bool, says: string} */
    private static function answer(Response $answer): array
    {
        return match (true) {
            in_array($answer->status(), [401, 403], true) => self::no('refused'),
            $answer->status() === 404 => self::no('not_found'),
            default => self::no('status', ['status' => (string) $answer->status()]),
        };
    }

    /**
     * @param  array<string, string>  $with
     * @return array{ok: bool, says: string}
     */
    private static function yes(string $what, array $with = []): array
    {
        return ['ok' => true, 'says' => __("platform::settings.checks.ok.{$what}", $with)];
    }

    /**
     * @param  array<string, string>  $with
     * @return array{ok: bool, says: string}
     */
    private static function no(string $why, array $with = []): array
    {
        return ['ok' => false, 'says' => __("platform::settings.checks.no.{$why}", $with)];
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
