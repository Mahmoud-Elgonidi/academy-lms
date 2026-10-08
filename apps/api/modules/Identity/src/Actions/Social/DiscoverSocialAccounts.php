<?php

namespace Modules\Identity\Actions\Social;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Identity\Models\SocialSignIn;
use Modules\Identity\Social\SocialProfile;
use Modules\Platform\Contracts\AccountDirectory;
use Modules\Platform\Contracts\DTOs\DiscoveredAccount;

/**
 * The shared app's way in with Google or Apple, like a verified code: the
 * person's accounts in every active academy (linked to that Google or Apple
 * account, or carrying the email it verified), and a discovery token the app
 * trades for a sign-in to each one it adds (SignInDiscoveredAccount).
 */
final class DiscoverSocialAccounts
{
    public function __construct(private readonly AccountDirectory $accounts) {}

    /** @return array{token: string, expires_at: CarbonImmutable, accounts: list<DiscoveredAccount>} */
    public function handle(SocialProfile $profile): array
    {
        $found = $this->accounts->withSocialIdentity($profile->provider->value, $profile->subject);
        if (($email = $profile->matchableEmail()) !== null) {
            $found = [...$found, ...$this->accounts->withEmail($email)];
        }
        $accounts = collect($found)->unique(fn (DiscoveredAccount $a) => $a->userId)->values()->all();

        $token = Str::random(48);
        $expiresAt = CarbonImmutable::now()->addMinutes((int) config('identity.otp.discovery_minutes'));
        SocialSignIn::query()->create([
            'purpose' => 'discover', 'provider' => $profile->provider, 'subject' => $profile->subject,
            'email' => $profile->email, 'email_verified' => $profile->emailVerified,
            'token_hash' => SocialSignIn::hash($token), 'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt, 'accounts' => $accounts];
    }
}
