<?php

namespace Modules\Identity\Social;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Identity\Models\SocialSignIn;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * Signing in with Google or Apple in a browser. Google and Apple send
 * everyone back to one address on the platform (registered with each), so
 * the academy, who started it (a panel or the portal), what for (sign in, or
 * link the account of someone signed in) and the browser's binding travel in
 * an encrypted state with the nonce and Google's PKCE verifier. The callback
 * checks what the provider says, then hands it to the academy's own host as
 * a ticket that works once, for two minutes, and only for the browser that
 * started (its secret's hash is in the state): a link someone else opened
 * signs nobody in.
 */
final class SocialFlow
{
    public function __construct(
        private readonly IdTokens $tokens,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * Where to send the browser to sign in with the provider.
     *
     * @param  string  $client  panel:{id} | portal
     * @param  string  $intent  sign_in | link
     * @param  string  $bindHash  SHA-256 of a secret only the starting browser holds
     */
    public function authorizeUrl(Provider $provider, string $client, string $intent, ?string $userId, string $bindHash, string $locale): string
    {
        $nonce = Str::random(32);
        $verifier = Str::random(64);
        $state = Crypt::encryptString((string) json_encode([
            'a' => $this->tenants->require()->id, 'p' => $provider->value, 'c' => $client, 'i' => $intent, 'u' => $userId,
            'b' => $bindHash, 'n' => $nonce, 'v' => $verifier, 'l' => $locale,
            'x' => CarbonImmutable::now()->addMinutes((int) config('identity.social.state_minutes'))->getTimestamp(),
        ]));
        $config = $provider->config();

        $query = match ($provider) {
            Provider::Google => [
                'client_id' => $config['client_id'], 'redirect_uri' => $this->redirectUri($provider), 'response_type' => 'code',
                'scope' => 'openid email profile', 'state' => $state, 'nonce' => $nonce, 'prompt' => 'select_account',
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
            ],
            // Apple answers by POST when it is asked for the email.
            Provider::Apple => [
                'client_id' => $config['client_id'], 'redirect_uri' => $this->redirectUri($provider), 'response_type' => 'code id_token',
                'response_mode' => 'form_post', 'scope' => 'email', 'state' => $state, 'nonce' => $nonce,
            ],
        };

        return $config['authorize_url'].'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The state the provider sent back, if it is ours and fresh.
     *
     * @return ?array{a: string, p: string, c: string, i: string, u: ?string, b: string, n: string, v: string, l: string, x: int}
     */
    public function readState(string $state): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($state), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) && isset($data['a'], $data['p'], $data['c'], $data['i'], $data['b'], $data['n'], $data['x'])
            && (int) $data['x'] >= CarbonImmutable::now()->getTimestamp() ? $data : null;
    }

    /**
     * Who the provider says signed in, from its answer on the callback.
     *
     * @param  array{n: string, v: string}  $state
     *
     * @throws InvalidIdToken
     */
    public function profileFrom(Provider $provider, Request $request, array $state): SocialProfile
    {
        $config = $provider->config();
        $idToken = match ($provider) {
            Provider::Google => $this->googleIdToken((string) $request->input('code'), (string) $state['v']),
            Provider::Apple => (string) $request->input('id_token'),
        };

        return $this->tokens->verify($provider, $idToken, [(string) $config['client_id']], (string) $state['n']);
    }

    /** The address on the platform the provider sends people back to. */
    public function redirectUri(Provider $provider): string
    {
        $path = route('identity.social.callback', ['provider' => $provider->value], absolute: false);
        $base = config('identity.social.redirect_base');

        return filled($base) ? rtrim((string) $base, '/').$path : url($path);
    }

    /**
     * A ticket for the academy's own host, from the platform's callback.
     *
     * @param  array{a: string, c: string, i: string, u: ?string, b: string}  $state
     */
    public function ticket(SocialProfile $profile, array $state): string
    {
        $token = Str::random(48);
        SocialSignIn::query()->create([
            'purpose' => 'handoff',
            'academy_id' => $state['a'],
            'provider' => $profile->provider,
            'subject' => $profile->subject,
            'email' => $profile->email,
            'email_verified' => $profile->emailVerified,
            'intent' => $state['i'],
            'user_id' => $state['i'] === 'link' ? $state['u'] : null,
            'client' => $state['c'],
            'bind_hash' => $state['b'],
            'token_hash' => SocialSignIn::hash($token),
            'expires_at' => CarbonImmutable::now()->addSeconds((int) config('identity.social.ticket_seconds')),
        ]);

        return $token;
    }

    /**
     * The ticket, once, on the academy it was made for, from the browser that started (its secret).
     *
     * @throws SocialTicketRefused
     */
    public function redeem(string $ticket, string $bindSecret, string $intent): SocialSignIn
    {
        $row = SocialSignIn::query()->where('token_hash', SocialSignIn::hash($ticket))->where('purpose', 'handoff')->first();
        $ok = $row !== null && $row->academy_id === $this->tenants->require()->id && $row->intent === $intent
            && $row->expires_at->isFuture() && hash_equals((string) $row->bind_hash, hash('sha256', $bindSecret));
        // Used once: the first to mark it wins.
        if (! $ok || SocialSignIn::query()->whereKey($row->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) !== 1) {
            throw new SocialTicketRefused;
        }

        return $row;
    }

    private function googleIdToken(string $code, string $verifier): string
    {
        $config = Provider::Google->config();
        if ($code === '') {
            throw new InvalidIdToken('no code');
        }
        try {
            $response = Http::asForm()->timeout(15)->acceptJson()->post((string) $config['token_url'], [
                'grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $verifier,
                'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'], 'redirect_uri' => $this->redirectUri(Provider::Google),
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new InvalidIdToken('exchange');
        }

        return is_string($response->json('id_token')) ? $response->json('id_token') : throw new InvalidIdToken('exchange');
    }
}
