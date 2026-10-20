<?php

namespace Modules\Platform\Settings;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Ai\AiProbe;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\Faults\ElevenLabsFaults;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\Platform\Health\TestMail;
use Modules\SharedKernel\Health\Faults\FcmFaults;
use Modules\SharedKernel\Health\Faults\LiveKitFaults;
use Modules\SharedKernel\Health\Faults\MetaFaults;
use Modules\SharedKernel\Health\Faults\StripeFaults;
use Modules\SharedKernel\Health\Faults\TwilioFaults;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;
use Modules\SharedKernel\Health\TestAddresses;
use Throwable;

/**
 * "Try it" on the Platform settings screen, and the daily `platform:services`:
 * a few requests to each service with the settings in force (the screen's,
 * else the server's, or for a moment what is typed: PlatformSettings::trying)
 * that change nothing there (an email to the owner who asked aside). It says
 * in our words whether the service took the keys and, when not, why and what
 * to do (Health\Faults\*: the provider's code, never its words or a key), and
 * warns while a plan's credit is nearly used. With $record it writes what it
 * found to ServiceHealth (never for typed values, so a wrong key typed does
 * not mark the saved one broken).
 */
final class SettingChecks
{
    /** The sections that have a try. */
    public const GROUPS = ['ai', 'mail', 'whatsapp', 'sms', 'push', 'classroom', 'stripe'];

    private const TIMEOUT = 10;

    /** A plan this much used warns. */
    private const NEAR = 90;

    /** Twilio's balance under this warns (in the account's currency). */
    private const LOW_BALANCE = 5;

    private const UNPAID = ['past_due', 'unpaid', 'canceled', 'cancelled', 'incomplete_expired'];

    /** @var list<CheckLine> */
    private array $lines = [];

    /** @var array<string, list<array{0: ServiceFault, 1: string}>> what each service tried got: its faults and their scopes */
    private array $faults = [];

    /** @var array<string, ?int> how much of each plan is used, where a check read it */
    private array $near = [];

    public function __construct(private readonly AiProbe $probe) {}

    public function run(string $group, ?string $ownerEmail = null, bool $record = true): CheckResult
    {
        $this->lines = [];
        $this->faults = [];
        $this->near = [];
        match ($group) {
            'ai' => $this->ai(),
            'mail' => $this->mail($ownerEmail),
            'whatsapp' => $this->whatsapp(),
            'sms' => $this->sms(),
            'push' => $this->push(),
            'classroom' => $this->classroom(),
            'stripe' => $this->stripe(),
            default => $this->line(null, 'info', 'none'),
        };
        if ($record) {
            $this->record();
        }

        return new CheckResult($this->lines);
    }

    private function ai(): void
    {
        $driver = (string) config('platform.ai.driver');
        $provider = AiProvider::current();
        if ($driver === 'off') {
            $this->line(null, 'info', 'ai_off');
        } elseif ($provider === null) {
            // The demo's answers, or "demo" left on a production server (where it is no one).
            PlatformAcademyAi::demo() ? $this->line(null, 'info', 'ai_demo') : $this->line(null, 'fail', 'ai_demo_production');
        } elseif (! $provider->hasCredentials()) {
            $this->missing($provider->service());
        } elseif ($provider->model('best') === '') {
            $this->line($provider->service(), 'fail', 'no_model');
        } else {
            foreach (array_unique([$provider->model('best'), $provider->model('fast')]) as $model) {
                // A key refused or a credit run out is the provider's, whatever the model: said once.
                if (! $this->model($provider, $model, 'model_ok')) {
                    break;
                }
            }
        }

        $designer = (array) config('platform.games.designer');
        $model = trim((string) ($designer['model'] ?? ''));
        if (($designer['driver'] ?? null) === 'claude' && $model !== '') {
            $key = trim((string) ($designer['key'] ?? ''));
            $key === '' ? $this->missing('anthropic') : $this->model(AiProvider::Claude, $model, 'designer_ok', $key, (string) (($designer['base_url'] ?? null) ?: AiProvider::Claude->baseUrl()));
        }

        if ($provider !== null || PlatformAcademyAi::demo()) {
            $this->speech();
        }
    }

    /** Whether the provider answered for this model (a model it does not offer is that model's alone). */
    private function model(AiProvider $provider, string $model, string $ok, ?string $key = null, ?string $base = null): bool
    {
        $service = $provider->service();
        $fault = $this->probe->probe($provider, $model, $key, $base);
        if ($fault === null) {
            $this->ok($service, $ok, ['model' => $model]);
        } elseif ($fault->problem === ServiceProblem::Model) {
            $this->fault($service, $fault, $model, 'model_missing', ['model' => $model]);
        } else {
            $this->fault($service, $fault, $model);

            return false;
        }

        return true;
    }

    /** ElevenLabs: the key, and how much of the plan is used. */
    private function speech(): void
    {
        $key = trim((string) config('platform.ai.speech.key'));
        if ($key === '') {
            $this->line(null, 'info', 'speech_none');

            return;
        }
        $this->reach('elevenlabs', function () use ($key) {
            $answer = Http::withHeaders(['xi-api-key' => $key])->acceptJson()->timeout(self::TIMEOUT)
                ->get(rtrim((string) config('platform.ai.speech.base_url'), '/').'/v1/user/subscription');
            if (! $answer->successful()) {
                // A key limited to speech to text may not read the plan: it was taken all the same.
                if (data_get($answer->json(), 'detail.status') === 'missing_permissions') {
                    $this->ok('elevenlabs', 'speech_limited');

                    return;
                }
                $this->fault('elevenlabs', ElevenLabsFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status()));

                return;
            }
            $status = strtolower((string) $answer->json('status', ''));
            $limit = (int) $answer->json('character_limit', 0);
            $pct = $limit > 0 ? (int) floor((int) $answer->json('character_count', 0) * 100 / $limit) : null;
            $renews = (int) $answer->json('next_character_count_reset_unix', 0);
            $this->near['elevenlabs'] = $pct;
            if (in_array($status, self::UNPAID, true)) {
                $this->fault('elevenlabs', new ServiceFault(ServiceProblem::Quota, $status));
            } elseif ($pct !== null && $pct >= 100) {
                $this->fault('elevenlabs', new ServiceFault(ServiceProblem::Quota, 'character_limit'));
            } elseif ($pct !== null && $pct >= self::NEAR) {
                $this->line('elevenlabs', 'warn', $renews > 0 ? 'near_renews' : 'near', ['pct' => $pct, 'when' => $this->day($renews)], 'platform::services.todo.quota');
                $this->faults['elevenlabs'] ??= [];
            } else {
                $this->ok('elevenlabs', $pct !== null ? 'speech_ok' : 'speech_taken', ['pct' => (int) $pct]);
            }
        });
    }

    private function mail(?string $email): void
    {
        if (config('mail.default') !== 'smtp') {
            $this->line(null, 'fail', 'mail_log');

            return;
        }
        if ($email === null) {
            $this->line(null, 'info', 'mail_nobody');

            return;
        }
        // A demo address never reaches a mail server (TestAddresses): saying "sent" would mislead.
        if (TestAddresses::skips($email)) {
            $this->line(null, 'fail', 'mail_demo', ['email' => $email]);

            return;
        }
        try {
            Mail::to($email)->send(new TestMail);
        } catch (Throwable $e) {
            MailHealth::failed($e);
            $this->line(null, 'fail', 'mail_failed');

            return;
        }
        $this->line(null, 'ok', 'mail', ['email' => $email]);
    }

    private function whatsapp(): void
    {
        $meta = (array) config('communication.whatsapp.meta');
        if (blank($meta['token'] ?? null) || blank($meta['phone_number_id'] ?? null)) {
            $this->missing('whatsapp');

            return;
        }
        $this->reach('whatsapp', function () use ($meta) {
            $answer = Http::withToken((string) $meta['token'])->acceptJson()->timeout(self::TIMEOUT)
                ->get('https://graph.facebook.com/'.($meta['api_version'] ?? 'v21.0').'/'.rawurlencode((string) $meta['phone_number_id']), ['fields' => 'display_phone_number,quality_rating']);
            if (! $answer->successful()) {
                $this->fault('whatsapp', MetaFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status()));

                return;
            }
            $this->ok('whatsapp', 'whatsapp_ok', ['number' => (string) $answer->json('display_phone_number')]);
            if (strtoupper((string) $answer->json('quality_rating')) === 'RED') {
                $this->line('whatsapp', 'warn', 'whatsapp_red');
            }
        });
    }

    private function sms(): void
    {
        $twilio = (array) config('communication.sms.twilio');
        if (blank($twilio['sid'] ?? null) || blank($twilio['token'] ?? null)) {
            $this->missing('twilio');

            return;
        }
        $this->reach('twilio', function () use ($twilio) {
            $http = fn () => Http::withBasicAuth((string) $twilio['sid'], (string) $twilio['token'])->acceptJson()->timeout(self::TIMEOUT);
            $account = 'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode((string) $twilio['sid']);
            $answer = $http()->get($account.'.json');
            if (! $answer->successful()) {
                $this->fault('twilio', TwilioFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status()));

                return;
            }
            $status = strtolower((string) $answer->json('status'));
            if (in_array($status, ['suspended', 'closed'], true)) {
                $this->fault('twilio', new ServiceFault(ServiceProblem::Suspended, $status));

                return;
            }
            if (strtolower((string) $answer->json('type')) === 'trial') {
                $this->line('twilio', 'warn', 'twilio_trial');
            }

            $balance = $http()->get($account.'/Balance.json');
            // A subaccount, or a key that may not read the balance: the account answered all the same.
            if (! $balance->successful() || ! is_numeric($balance->json('balance'))) {
                $this->ok('twilio', 'twilio_ok');

                return;
            }
            $left = (float) $balance->json('balance');
            $with = ['balance' => number_format($left, 2), 'currency' => (string) $balance->json('currency')];
            if ($left <= 0) {
                $this->fault('twilio', new ServiceFault(ServiceProblem::Quota, 'balance'));
            } elseif ($left < self::LOW_BALANCE) {
                $this->line('twilio', 'warn', 'twilio_low', $with, 'platform::services.todo.quota');
                $this->faults['twilio'] ??= [];
            } else {
                $this->ok('twilio', 'twilio_balance', $with);
            }
        });
    }

    private function push(): void
    {
        $fcm = (array) config('communication.push.fcm');
        $json = (string) ($fcm['credentials_json'] ?? '');
        if ($json === '' && is_file((string) ($fcm['credentials'] ?? ''))) {
            $json = (string) file_get_contents((string) $fcm['credentials']);
        }
        $account = json_decode($json, true);
        $project = (string) ($fcm['project_id'] ?? '');
        if ($project === '' || ! is_array($account) || ! isset($account['client_email'], $account['private_key'])) {
            $this->missing('fcm');

            return;
        }
        $now = time();
        $segments = array_map(fn (array $part) => self::base64url((string) json_encode($part)), [
            ['alg' => 'RS256', 'typ' => 'JWT'],
            ['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 300],
        ]);
        if (! @openssl_sign(implode('.', $segments), $signature, (string) $account['private_key'], OPENSSL_ALGO_SHA256)) {
            $this->line('fcm', 'fail', 'push_key', [], 'platform::services.todo.key');

            return;
        }
        $this->reach('fcm', function () use ($segments, $signature, $project) {
            $token = Http::asForm()->timeout(self::TIMEOUT)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments).'.'.self::base64url($signature),
            ]);
            if (! $token->successful() || blank($token->json('access_token'))) {
                $this->fault('fcm', FcmFaults::token($token->status(), $token->json()));

                return;
            }
            // Checked by Firebase as a real send would be, and sent to no one (validate_only).
            $send = Http::withToken((string) $token->json('access_token'))->acceptJson()->timeout(self::TIMEOUT)
                ->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($project).'/messages:send', [
                    'validate_only' => true,
                    'message' => ['topic' => 'platform-settings-check', 'data' => ['check' => '1']],
                ]);
            $send->successful()
                ? $this->ok('fcm', 'push_ok')
                : $this->fault('fcm', FcmFaults::send($send->status(), $send->json()) ?? ServiceFault::http($send->status()));
        });
    }

    private function classroom(): void
    {
        $livekit = (array) config('classroom.livekit');
        if (blank($livekit['key'] ?? null) || blank($livekit['secret'] ?? null) || blank($livekit['api_url'] ?? null)) {
            $this->missing('livekit');

            return;
        }
        $api = rtrim((string) $livekit['api_url'], '/');
        $this->reach('livekit', function () use ($livekit, $api) {
            $answer = $this->twirp($livekit, $api.'/twirp/livekit.RoomService/ListRooms', ['roomList' => true]);
            $answer->successful()
                ? $this->ok('livekit', 'livekit_ok')
                : $this->fault('livekit', LiveKitFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status()), 'rooms');
        });

        $egress = rtrim((string) ($livekit['egress_url'] ?? ''), '/');
        if ($egress !== '' && $egress !== $api) {
            $this->reach('livekit', function () use ($livekit, $egress) {
                $answer = $this->twirp($livekit, $egress.'/twirp/livekit.Egress/ListEgress', ['roomRecord' => true]);
                $answer->successful()
                    ? $this->ok('livekit', 'egress_ok')
                    : $this->fault('livekit', LiveKitFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status()), 'egress');
            }, 'egress');
        }
    }

    /**
     * The server API's own sign-in: a short token signed with the server's secret.
     *
     * @param  array<string, mixed>  $livekit
     * @param  array<string, bool>  $grant
     */
    private function twirp(array $livekit, string $url, array $grant): Response
    {
        $now = time();
        $unsigned = self::base64url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])).'.'
            .self::base64url((string) json_encode(['iss' => $livekit['key'], 'sub' => 'platform-settings', 'nbf' => $now - 10, 'exp' => $now + 120, 'video' => $grant]));
        $jwt = $unsigned.'.'.self::base64url(hash_hmac('sha256', $unsigned, (string) $livekit['secret'], true));

        return Http::withToken($jwt)->acceptJson()->timeout(self::TIMEOUT)->post($url, (object) []);
    }

    private function stripe(): void
    {
        $secret = (string) config('billing.payments.stripe.platform_secret');
        if ($secret === '') {
            $this->missing('stripe');

            return;
        }
        $this->reach('stripe', function () use ($secret) {
            $answer = Http::withBasicAuth($secret, '')->acceptJson()->timeout(self::TIMEOUT)
                ->get(rtrim((string) config('billing.payments.stripe.base', 'https://api.stripe.com'), '/').'/v1/account');
            if (! $answer->successful()) {
                $this->fault('stripe', StripeFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status()));

                return;
            }
            $disabled = (string) $answer->json('requirements.disabled_reason', '');
            if ($disabled !== '') {
                $this->fault('stripe', new ServiceFault(ServiceProblem::Suspended, $disabled));

                return;
            }
            $this->ok('stripe', 'stripe_ok');
            if ($answer->json('charges_enabled') === false) {
                $this->line('stripe', 'warn', 'stripe_charges_off');
            }
            $deadline = (int) $answer->json('requirements.current_deadline', 0);
            if ((array) $answer->json('requirements.currently_due', []) !== []) {
                $this->line('stripe', 'warn', $deadline > 0 ? 'stripe_due_by' : 'stripe_due', ['when' => $this->day($deadline)]);
            }
        });
        if (blank(config('billing.payments.stripe.connect_client_id'))) {
            $this->line(null, 'info', 'stripe_no_connect');
        }
    }

    /** Runs a service's requests; one it cannot reach says so. */
    private function reach(string $service, Closure $requests, string $scope = ''): void
    {
        try {
            $requests();
        } catch (ConnectionException) {
            $this->fault($service, ServiceFault::unreachable(), $scope);
        }
    }

    /** @param  array<string, string|int>  $with */
    private function ok(string $service, string $says, array $with = []): void
    {
        $this->faults[$service] ??= [];
        $this->line($service, 'ok', $says, $with);
    }

    /**
     * What a service would not do, why and what to do: a problem someone has
     * to act on fails, one that may pass on its own warns.
     *
     * @param  array<string, string|int>  $with
     */
    private function fault(string $service, ServiceFault $fault, string $scope = '', ?string $says = null, array $with = []): void
    {
        $this->faults[$service][] = [$fault, $scope];
        $this->lines[] = new CheckLine(
            $service,
            $fault->problem->transient() ? 'warn' : 'fail',
            $says !== null ? 'platform::settings.checks.lines.'.$says : 'platform::services.problems.'.$fault->problem->value,
            $with,
            'platform::services.todo.'.$fault->problem->value,
            $fault->code,
        );
    }

    private function missing(string $service): void
    {
        $this->line($service, 'fail', 'missing');
    }

    /** @param  array<string, string|int>  $with */
    private function line(?string $service, string $level, string $says, array $with = [], ?string $todo = null): void
    {
        $line = new CheckLine($service, $level, 'platform::settings.checks.lines.'.$says, $with, $todo);
        // The helpers and the games designer share Claude's key: one line when it is missing.
        if (! in_array($line, $this->lines, false)) {
            $this->lines[] = $line;
        }
    }

    /** What each service tried got, into ServiceHealth: its first fault that needs someone (else its first), or that it answered. */
    private function record(): void
    {
        foreach ($this->faults as $service => $faults) {
            $worst = collect($faults)->first(fn (array $f) => $f[0]->problem->severe()) ?? ($faults[0] ?? null);
            $worst === null
                ? ServiceHealth::ok($service, '', 'check')
                : ServiceHealth::fault($service, $worst[0], $worst[1], 'check');
        }
        foreach ($this->near as $service => $pct) {
            ServiceHealth::near($service, $pct);
        }
    }

    /** A day as the provider gave it (UTC), for a line kept and read later. */
    private function day(int $timestamp): string
    {
        return $timestamp > 0 ? gmdate('Y-m-d', $timestamp) : '';
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
