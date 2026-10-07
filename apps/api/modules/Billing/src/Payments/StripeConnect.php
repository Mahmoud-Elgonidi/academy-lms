<?php

namespace Modules\Billing\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Connect Standard, the platform's side (docs/architecture/03,
 * section 5: "Connect Standard بـ direct charges"): send an academy's admin
 * to Stripe to connect the academy's own Stripe account, take the account
 * id back, and disconnect it. Stripe returns to one address on the
 * platform; which academy asked travels in an encrypted state that lasts
 * twenty minutes.
 */
final class StripeConnect
{
    private const STATE_MINUTES = 20;

    public function authorizeUrl(string $academyId, string $userId): string
    {
        $state = Crypt::encryptString(json_encode(['a' => $academyId, 'u' => $userId, 'x' => CarbonImmutable::now()->addMinutes(self::STATE_MINUTES)->getTimestamp()]));

        return rtrim((string) config('billing.payments.stripe.connect_base'), '/').'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('billing.payments.stripe.connect_client_id'),
            'scope' => 'read_write',
            'state' => $state,
            'redirect_uri' => $this->redirectUri(),
            'stripe_landing' => 'login',
        ]);
    }

    /** @return ?array{academy: string, user: string} who asked, from a state Stripe sent back; null when forged or too old */
    public function readState(string $state): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($data) && isset($data['a'], $data['u'], $data['x']) && $data['x'] >= CarbonImmutable::now()->getTimestamp()
            ? ['academy' => (string) $data['a'], 'user' => (string) $data['u']] : null;
    }

    /**
     * Trades the code Stripe sent back for the connected account.
     *
     * @return array{account: string, live: bool}
     */
    public function exchange(string $code): array
    {
        $body = $this->post('/oauth/token', ['grant_type' => 'authorization_code', 'code' => $code]);
        if (empty($body['stripe_user_id']) || ! str_starts_with((string) $body['stripe_user_id'], 'acct_')) {
            throw new GatewayException('Stripe Connect: no account came back.');
        }

        return ['account' => (string) $body['stripe_user_id'], 'live' => (bool) ($body['livemode'] ?? false)];
    }

    /** Lets the connected account go: the platform can no longer charge on it. */
    public function deauthorize(string $accountId): void
    {
        $this->post('/oauth/deauthorize', ['client_id' => config('billing.payments.stripe.connect_client_id'), 'stripe_user_id' => $accountId]);
    }

    public function redirectUri(): string
    {
        return (string) (config('billing.payments.stripe.connect_redirect') ?: url(route('billing.stripe.connected', absolute: false)));
    }

    /**
     * @param  array<string, string>  $form
     * @return array<string, mixed>
     */
    private function post(string $path, array $form): array
    {
        try {
            $response = Http::baseUrl((string) config('billing.payments.stripe.connect_base'))
                ->withToken((string) config('billing.payments.stripe.platform_secret'))
                ->asForm()->acceptJson()->timeout((int) config('billing.payments.timeout'))
                ->post($path, $form);
        } catch (ConnectionException $e) {
            throw new GatewayException('Stripe could not be reached: '.$e->getMessage(), previous: $e);
        }
        if ($response->failed()) {
            throw new GatewayException('Stripe Connect: '.($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
