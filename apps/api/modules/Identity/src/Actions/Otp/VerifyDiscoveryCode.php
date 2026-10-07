<?php

namespace Modules\Identity\Actions\Otp;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;
use Modules\Platform\Contracts\AccountDirectory;
use Modules\Platform\Contracts\DTOs\DiscoveredAccount;

/**
 * The code is right: the person's accounts in every active academy, and a
 * discovery token the app trades for a sign-in to each one it adds
 * (SignInDiscoveredAccount), for a few minutes.
 */
final class VerifyDiscoveryCode
{
    public function __construct(private readonly Challenges $challenges, private readonly AccountDirectory $accounts) {}

    /** @return array{token: string, expires_at: CarbonImmutable, accounts: list<DiscoveredAccount>} */
    public function handle(string $challengeId, string $code): array
    {
        $challenge = $this->challenges->verify($challengeId, $code, OtpPurpose::Discover, null);

        $token = Str::random(48);
        $challenge->forceFill(['token_hash' => Challenges::tokenHash($token)])->save();

        $contact = str_contains($challenge->destination, '@') ? Contact::email($challenge->destination) : Contact::phone($challenge->destination);

        return [
            'token' => $token,
            'expires_at' => $challenge->verified_at->addMinutes((int) config('identity.otp.discovery_minutes')),
            'accounts' => $contact->isEmail() ? $this->accounts->withEmail($contact->value) : $this->accounts->withPhone($contact->value),
        ];
    }
}
