<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\Social\LinkSocialIdentity;
use Modules\Identity\Actions\Social\UnlinkSocialIdentity;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\SignIn;
use Modules\Identity\Models\SocialIdentity;
use Modules\Identity\Models\User;
use Modules\Identity\Social\IdTokens;
use Modules\Identity\Social\InvalidIdToken;
use Modules\Identity\Social\Provider;
use Modules\Identity\Social\SocialProfile;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Audit\AuditActor;
use PragmaRX\Google2FA\Google2FA;

/** Google or Apple, as far as the tests go: its own key, its published keys, and ID tokens signed with it. */
final class FakeIdentityProvider
{
    private static array $keys = [];

    public function __construct(public readonly string $issuer, public readonly string $kid = 'key-1') {}

    public function jwks(): array
    {
        $details = openssl_pkey_get_details($this->key());

        return ['keys' => [['kty' => 'RSA', 'kid' => $this->kid, 'alg' => 'RS256', 'use' => 'sig', 'n' => self::b64($details['rsa']['n']), 'e' => self::b64($details['rsa']['e'])]]];
    }

    public function token(array $claims, ?string $kid = null): string
    {
        $head = self::b64((string) json_encode(['alg' => 'RS256', 'kid' => $kid ?? $this->kid, 'typ' => 'JWT']));
        $body = self::b64((string) json_encode($claims + ['iss' => $this->issuer, 'iat' => now()->getTimestamp(), 'exp' => now()->addHour()->getTimestamp()]));
        openssl_sign("{$head}.{$body}", $signature, $this->key(), OPENSSL_ALGO_SHA256);

        return "{$head}.{$body}.".self::b64($signature);
    }

    private function key(): OpenSSLAsymmetricKey
    {
        return self::$keys[$this->issuer.$this->kid] ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

beforeEach(function () {
    config([
        'identity.social.redirect_base' => 'http://lms.test',
        'identity.social.google.client_id' => 'web-client.apps.googleusercontent.com',
        'identity.social.google.client_secret' => 'google-secret',
        'identity.social.google.app_audiences' => 'android-client.apps.googleusercontent.com,ios-client.apps.googleusercontent.com',
        'identity.social.google.jwks_url' => 'https://keys.test/google',
        'identity.social.google.token_url' => 'https://oauth.test/google/token',
        'identity.social.google.authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'identity.social.google.issuers' => 'https://accounts.google.com,accounts.google.com',
        'identity.social.apple.authorize_url' => 'https://appleid.apple.com/auth/authorize',
        'identity.social.apple.issuers' => 'https://appleid.apple.com',
        'identity.social.apple.client_id' => 'org.academy.web',
        'identity.social.apple.app_audiences' => 'org.academy.app',
        'identity.social.apple.jwks_url' => 'https://keys.test/apple',
    ]);
    $this->google = new FakeIdentityProvider('https://accounts.google.com');
    $this->apple = new FakeIdentityProvider('https://appleid.apple.com');
    $this->nextGoogleToken = null;
    Http::fake([
        'keys.test/google' => fn () => Http::response($this->google->jwks()),
        'keys.test/apple' => fn () => Http::response($this->apple->jwks()),
        'oauth.test/google/token' => fn () => Http::response(['id_token' => $this->nextGoogleToken, 'token_type' => 'Bearer']),
    ]);

    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_en' => 'Noor']));
    $this->huda = User::factory()->withRole(Role::Guardian)->create(['name' => 'Huda', 'email' => 'huda@example.test', 'password' => 'a-long-password']);
    $this->teacher = User::factory()->withRole(Role::Teacher)->withTwoFactor()->create(['name' => 'Khaled', 'email' => 'khaled@example.test']);
    $this->rawda = $this->actAsTenant($this->createAcademy(['slug' => 'rawda', 'name_en' => 'Rawda']));
    $this->hudaAtRawda = User::factory()->withRole(Role::Guardian)->create(['name' => 'Huda A.', 'email' => 'huda.rawda@example.test']);
    $this->clearTenant();
    // The academies' own addresses, as the platform makes them here (APP_URL's scheme and port).
    $this->noorUrl = rtrim(app(TenantDirectory::class)->baseUrl($this->noor), '/');
    $this->rawdaUrl = rtrim(app(TenantDirectory::class)->baseUrl($this->rawda), '/');
});

afterEach(fn () => AuditActor::forget());

function googleToken(array $claims = []): string
{
    return test()->google->token($claims + ['aud' => 'android-client.apps.googleusercontent.com', 'sub' => 'g-1001', 'email' => 'huda@example.test', 'email_verified' => true]);
}

function socialToken(array $body, string $academy = 'noor')
{
    return test()->withHeaders(['X-Academy' => $academy])->postJson('/api/v1/auth/social', $body + ['provider' => 'google', 'device_name' => 'Pixel 9']);
}

/** The state the start step put in the provider's address, opened (only the platform can). */
function stateOf(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return json_decode(Crypt::decryptString($query['state']), true) + ['raw' => $query['state'], 'query' => $query];
}

it('checks an ID token: the provider\'s signature, the issuer, one of our clients, its times and the nonce', function () {
    $tokens = app(IdTokens::class);
    $google = Provider::Google;
    $ok = $tokens->verify($google, googleToken(['nonce' => 'n-1']), ['android-client.apps.googleusercontent.com'], 'n-1');
    expect($ok->subject)->toBe('g-1001')->and($ok->email)->toBe('huda@example.test')->and($ok->emailVerified)->toBeTrue();
    // Apple's apps put the nonce's SHA-256 in the token.
    expect($tokens->verify(Provider::Apple, $this->apple->token(['aud' => 'org.academy.app', 'sub' => 'a-1', 'nonce' => hash('sha256', 'raw'), 'email' => 'x@privaterelay.appleid.com', 'email_verified' => 'true']), ['org.academy.app'], 'raw')->emailVerified)->toBeTrue();

    $refused = fn (string $token, array $audiences = ['android-client.apps.googleusercontent.com'], ?string $nonce = null) => expect(fn () => $tokens->verify($google, $token, $audiences, $nonce))->toThrow(InvalidIdToken::class);
    $refused(googleToken(['aud' => 'someone-else']));                                           // another app's token
    $refused(googleToken(['nonce' => 'n-1']), nonce: 'n-2');                                   // not this sign-in's
    $refused(googleToken(['exp' => now()->subMinutes(5)->getTimestamp()]));                     // expired
    $refused($this->apple->token(['aud' => 'android-client.apps.googleusercontent.com', 'sub' => 'x']));   // Apple's key, Google's door
    $refused((new FakeIdentityProvider('https://accounts.google.com', 'key-2'))->token(['aud' => 'android-client.apps.googleusercontent.com', 'sub' => 'x'], 'key-2'));   // a key Google never published
    $refused(str_replace('.', '.x', googleToken()));
    $refused('not-a-token');
});

it('signs in with the phone\'s Google sign-in by the verified email, links it, and keeps signing in when the email changes', function () {
    $response = socialToken(['id_token' => googleToken()])->assertOk()->assertJsonPath('user.name', 'Huda');
    expect($response->json('token'))->toBeString();

    $this->actAsTenant($this->noor);
    $link = SocialIdentity::query()->where('user_id', $this->huda->id)->sole();
    expect($link->provider)->toBe(Provider::Google)->and($link->subject)->toBe('g-1001')->and($link->via)->toBe('email');
    expect(DB::table('audit_logs')->where('action', 'identity.social_linked')->where('subject_id', $this->huda->id)->exists())->toBeTrue();
    $this->clearTenant();

    // A new address at Google: the link signs her in, not the email.
    socialToken(['id_token' => googleToken(['email' => 'huda.new@gmail.com'])])->assertOk()->assertJsonPath('user.name', 'Huda');
    // Android's tokens are for the platform's web client: that counts too.
    socialToken(['id_token' => googleToken(['aud' => 'web-client.apps.googleusercontent.com'])])->assertOk();
});

it('signs nobody in on an unverified email, another app\'s token, or an account that is not active', function () {
    socialToken(['id_token' => googleToken(['sub' => 'g-2', 'email_verified' => false])])->assertStatus(404)->assertJsonPath('type', fn ($t) => str_ends_with($t, 'social-account-not-found'));
    socialToken(['id_token' => googleToken(['aud' => 'org.someone.else'])])->assertStatus(401);
    socialToken(['id_token' => googleToken()], academy: 'rawda')->assertStatus(404);   // Huda has no account there with this email

    $this->actAsTenant($this->noor);
    $this->huda->forceFill(['status' => 'disabled'])->save();
    $this->clearTenant();
    socialToken(['id_token' => googleToken()])->assertStatus(404);
    $this->actAsTenant($this->noor);
    expect(SocialIdentity::query()->count())->toBe(0);
});

it('asks for the authenticator app\'s code when two-step sign-in is on', function () {
    socialToken(['id_token' => googleToken(['sub' => 'g-khaled', 'email' => 'khaled@example.test'])])
        ->assertStatus(403)
        ->assertJsonPath('type', fn ($t) => str_ends_with($t, 'two-factor-required'))
        ->assertJsonStructure(['two_factor_token']);
});

it('finds my accounts across academies with Apple, by a link or the verified email, and signs in to each', function () {
    $this->actAsTenant($this->rawda);
    SocialIdentity::query()->create(['user_id' => $this->hudaAtRawda->id, 'provider' => Provider::Apple, 'subject' => 'a-77', 'email' => 'q7@privaterelay.appleid.com', 'via' => 'settings', 'linked_at' => now()]);
    $this->clearTenant();

    $token = $this->apple->token(['aud' => 'org.academy.app', 'sub' => 'a-77', 'email' => 'huda@example.test', 'email_verified' => 'true', 'nonce' => hash('sha256', 'raw-nonce')]);
    $found = $this->postJson('/api/v1/accounts/social', ['provider' => 'apple', 'id_token' => $token, 'nonce' => 'raw-nonce'])->assertOk();
    expect(collect($found->json('accounts'))->pluck('academy.slug')->sort()->values()->all())->toBe(['noor', 'rawda']);

    foreach ([['noor', $this->huda], ['rawda', $this->hudaAtRawda]] as [$slug, $user]) {
        $this->withHeaders(['X-Academy' => $slug])->postJson('/api/v1/accounts/sign-in', [
            'discovery_token' => $found->json('discovery_token'), 'user_id' => $user->id, 'device_name' => 'iPhone',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }
    // Not an account it found.
    $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/accounts/sign-in', [
        'discovery_token' => $found->json('discovery_token'), 'user_id' => $this->teacher->id, 'device_name' => 'iPhone',
    ])->assertStatus(422);
});

it('signs in on a panel through the browser: the platform\'s callback, a ticket on the academy\'s host, then the panel', function () {
    $start = $this->get($this->noorUrl.'/auth/social/google/start?panel=family');
    $authorize = $start->headers->get('Location');
    expect($authorize)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->toContain('redirect_uri='.rawurlencode('http://lms.test/auth/social/google/callback'))
        ->toContain('code_challenge_method=S256');
    $state = stateOf($authorize);
    expect($state['a'])->toBe($this->noor->id)->and($state['c'])->toBe('panel:family');

    $this->nextGoogleToken = $this->google->token(['aud' => 'web-client.apps.googleusercontent.com', 'sub' => 'g-1001', 'email' => 'huda@example.test', 'email_verified' => true, 'nonce' => $state['n']]);
    $back = $this->get('http://lms.test/auth/social/google/callback?code=4/abc&state='.urlencode($state['raw']))->headers->get('Location');
    expect($back)->toStartWith($this->noorUrl.'/auth/social/finish?ticket=');
    Http::assertSent(fn ($request) => $request->url() === 'https://oauth.test/google/token' && $request['code_verifier'] === $state['v'] && $request['client_secret'] === 'google-secret');

    $this->get($back)->assertRedirect($this->noorUrl.'/my/login');

    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('family');
    Livewire::test(SignIn::class)->assertRedirect();
    expect(Auth::id())->toBe($this->huda->id);
});

it('asks the panel\'s authenticator step after Google when two-step sign-in is on', function () {
    $state = stateOf($this->get($this->noorUrl.'/auth/social/google/start?panel=teach')->headers->get('Location'));
    $this->nextGoogleToken = $this->google->token(['aud' => 'web-client.apps.googleusercontent.com', 'sub' => 'g-khaled', 'email' => 'khaled@example.test', 'email_verified' => true, 'nonce' => $state['n']]);
    $this->get($this->get('http://lms.test/auth/social/google/callback?code=c&state='.urlencode($state['raw']))->headers->get('Location'));

    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('teach');
    $page = Livewire::test(SignIn::class)->assertNoRedirect();
    expect(Auth::check())->toBeFalse();

    $google2fa = app(Google2FA::class);
    $page->set('data.multiFactor.app.code', $google2fa->oathTotp(UserFactory::TWO_FACTOR_SECRET, $google2fa->getTimestamp()))
        ->call('authenticate')
        ->assertRedirect();
    expect(Auth::id())->toBe($this->teacher->id);
});

it('takes Apple\'s answer by POST on the platform, and tells the panel when the person cancelled', function () {
    $state = stateOf($this->get($this->noorUrl.'/auth/social/apple/start?panel=family')->headers->get('Location'));
    expect($state['query']['response_mode'])->toBe('form_post');
    $token = $this->apple->token(['aud' => 'org.academy.web', 'sub' => 'a-1', 'email' => 'huda@example.test', 'email_verified' => 'true', 'nonce' => $state['n']]);
    $back = $this->post('http://lms.test/auth/social/apple/callback', ['state' => $state['raw'], 'code' => 'c', 'id_token' => $token])->headers->get('Location');
    expect($back)->toStartWith($this->noorUrl.'/auth/social/finish?ticket=');
    $this->get($back)->assertRedirect($this->noorUrl.'/my/login');

    // Cancelled at Apple: back to the panel's sign-in, saying so.
    $state = stateOf($this->get($this->noorUrl.'/auth/social/apple/start?panel=family')->headers->get('Location'));
    $back = $this->post('http://lms.test/auth/social/apple/callback', ['state' => $state['raw'], 'error' => 'user_cancelled_authorize'])->headers->get('Location');
    $this->get($back)->assertRedirect($this->noorUrl.'/my/login')->assertSessionHas('identity.social.error', __('identity::social.errors.denied', ['provider' => __('identity::social.providers.apple')]));
});

it('lets a ticket work once, only in the browser that started, on its own academy', function () {
    $state = stateOf($this->get($this->noorUrl.'/auth/social/google/start?panel=family')->headers->get('Location'));
    $this->nextGoogleToken = $this->google->token(['aud' => 'web-client.apps.googleusercontent.com', 'sub' => 'g-1001', 'email' => 'huda@example.test', 'email_verified' => true, 'nonce' => $state['n']]);
    $back = $this->get('http://lms.test/auth/social/google/callback?code=c&state='.urlencode($state['raw']))->headers->get('Location');

    // Someone else's browser (no secret): nothing.
    $this->flushSession();
    $this->get($back)->assertSessionHas('identity.social.error')->assertSessionMissing('identity.social.passed');
    // Another academy: nothing either.
    $this->get(str_replace($this->noorUrl, $this->rawdaUrl, $back))->assertSessionMissing('identity.social.passed');

    // A forged or stale state never reaches an academy.
    $this->get('http://lms.test/auth/social/google/callback?code=c&state=forged')->assertStatus(400);
    $this->travel(11)->minutes();
    $this->get('http://lms.test/auth/social/google/callback?code=c&state='.urlencode($state['raw']))->assertStatus(400);
});

it('lets the portal start, come back with a ticket, and sign in once with the browser\'s secret', function () {
    $secret = str_repeat('s', 40);
    $url = $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/social/google/start', ['bind' => hash('sha256', $secret), 'locale' => 'ar'])->assertOk()->json('url');
    $state = stateOf($url);
    expect($state['c'])->toBe('portal');
    $this->nextGoogleToken = $this->google->token(['aud' => 'web-client.apps.googleusercontent.com', 'sub' => 'g-1001', 'email' => 'huda@example.test', 'email_verified' => true, 'nonce' => $state['n']]);
    $back = $this->get('http://lms.test/auth/social/google/callback?code=c&state='.urlencode($state['raw']))->headers->get('Location');
    expect($back)->toStartWith($this->noorUrl.'/bff/social/finish?ticket=')->toContain('locale=ar');
    parse_str((string) parse_url($back, PHP_URL_QUERY), $query);

    $redeem = fn (string $bind) => $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/social/ticket', ['ticket' => $query['ticket'], 'bind' => $bind, 'device_name' => 'Portal']);
    $redeem('a-guess')->assertStatus(409);
    $redeem($secret)->assertOk()->assertJsonPath('user.name', 'Huda');
    $redeem($secret)->assertStatus(409);
});

it('links and unlinks from settings and the app, never someone else\'s Google account, the last way in, or while the platform team is signed in', function () {
    $this->actAsTenant($this->noor);
    $link = app(LinkSocialIdentity::class);
    $apple = new SocialProfile(Provider::Apple, 'a-9', 'q9@privaterelay.appleid.com', true);
    $link->handle($this->huda, $apple);
    expect(fn () => $link->handle($this->teacher, $apple))->toThrow(ValidationException::class);    // taken
    expect(fn () => $link->handle($this->huda, new SocialProfile(Provider::Apple, 'a-10', null, false)))->toThrow(ValidationException::class);   // one per provider

    // The relay address matches no account, but the link signs her in.
    $this->clearTenant();
    socialToken(['provider' => 'apple', 'id_token' => $this->apple->token(['aud' => 'org.academy.app', 'sub' => 'a-9', 'email' => 'q9@privaterelay.appleid.com', 'email_verified' => 'true'])])->assertOk()->assertJsonPath('user.name', 'Huda');

    // The app: list, link Google, unlink Apple.
    $this->actingAs($this->huda, 'sanctum');
    $this->withHeaders(['X-Academy' => 'noor'])->getJson('/api/v1/me/social')->assertOk()->assertJsonPath('data.1.provider', 'apple')->assertJsonPath('data.1.linked', true);
    $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/me/social', ['provider' => 'google', 'id_token' => googleToken(['sub' => 'g-5', 'email' => 'huda.other@gmail.com'])])
        ->assertOk()->assertJsonPath('data.0.linked', true)->assertJsonPath('data.0.email', 'huda.other@gmail.com');
    $this->withHeaders(['X-Academy' => 'noor'])->deleteJson('/api/v1/me/social/apple')->assertOk()->assertJsonPath('data.1.linked', false);

    // No password, email or number: Google is the only way in, so it stays.
    $this->actAsTenant($this->noor);
    $only = User::factory()->withRole(Role::Student)->create(['email' => null, 'phone_e164' => null, 'username' => 'only.google', 'password' => null]);
    $link->handle($only, new SocialProfile(Provider::Google, 'g-only', 'only@gmail.com', true));
    expect(fn () => app(UnlinkSocialIdentity::class)->handle($only, Provider::Google))->toThrow(ValidationException::class);

    AuditActor::use(new AuditActor('platform', null, 'Salma', $this->huda->id));
    expect(fn () => app(UnlinkSocialIdentity::class)->handle($this->huda, Provider::Google))->toThrow(ValidationException::class);
});

it('links Apple from a panel\'s My settings and comes back there, only for the person who started it', function () {
    // Starting a link needs someone signed in.
    $this->get($this->noorUrl.'/auth/social/apple/start?panel=family&link=1')->assertForbidden();

    $this->actingAs($this->huda);
    $state = stateOf($this->get($this->noorUrl.'/auth/social/apple/start?panel=family&link=1')->headers->get('Location'));
    expect($state['i'])->toBe('link')->and($state['u'])->toBe($this->huda->id);
    $token = $this->apple->token(['aud' => 'org.academy.web', 'sub' => 'a-huda', 'email' => 'h7@privaterelay.appleid.com', 'email_verified' => 'true', 'nonce' => $state['n']]);
    $back = $this->post('http://lms.test/auth/social/apple/callback', ['state' => $state['raw'], 'id_token' => $token])->headers->get('Location');

    $this->get($back)->assertRedirect($this->noorUrl.'/my/profile')->assertSessionHas('identity.social.done');
    $this->actAsTenant($this->noor);
    expect(SocialIdentity::query()->where('user_id', $this->huda->id)->where('provider', 'apple')->value('via'))->toBe('settings');

});
