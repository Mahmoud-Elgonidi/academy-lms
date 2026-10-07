<?php

namespace Modules\Identity\Actions\Otp;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Models\OtpChallenge;
use Modules\Identity\Models\User;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;

/**
 * One of the accounts discovery found, in the academy the request is for:
 * still active, and still carrying the email or number that was verified.
 */
final class SignInDiscoveredAccount
{
    public function handle(string $discoveryToken, string $userId): User
    {
        $challenge = OtpChallenge::query()
            ->where('token_hash', Challenges::tokenHash($discoveryToken))
            ->where('purpose', OtpPurpose::Discover->value)
            ->first();

        if ($challenge?->verified_at === null || $challenge->verified_at->addMinutes((int) config('identity.otp.discovery_minutes'))->isPast()) {
            throw ValidationException::withMessages(['discovery_token' => __('identity::otp.discovery_expired')]);
        }

        $user = Str::isUuid($userId) ? User::query()->find($userId) : null;
        $owns = $user !== null && in_array($challenge->destination_hash, array_filter([
            $user->email === null ? null : Contact::email($user->email)->hash(),
            $user->phone_e164 === null ? null : Contact::phone($user->phone_e164)->hash(),
        ]), true);

        return $owns && $user->isActive()
            ? $user
            : throw ValidationException::withMessages(['user_id' => __('identity::otp.not_yours')]);
    }
}
