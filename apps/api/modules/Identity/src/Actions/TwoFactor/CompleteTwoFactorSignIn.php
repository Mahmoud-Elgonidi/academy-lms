<?php

namespace Modules\Identity\Actions\TwoFactor;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\IssueAccessToken;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\PendingSignIns;
use Modules\Identity\TwoFactor\TwoFactor;

/**
 * An app's second step: the code its authenticator app shows now, or one of
 * the recovery codes (used up), for the sign-in the first step left waiting.
 * Five tries; then, or after five minutes, the person signs in again.
 */
final class CompleteTwoFactorSignIn
{
    public function __construct(
        private readonly PendingSignIns $pending,
        private readonly IssueAccessToken $tokens,
    ) {}

    /** @return array{user: User, token: string, expires_at: ?CarbonImmutable} */
    public function handle(string $token, string $code, ?string $deviceName = null): array
    {
        $step = $this->pending->find($token);
        $user = $step === null ? null : User::query()->find($step['user']);
        if ($user === null || ! $user->isActive() || ! TwoFactor::enabledFor($user)) {
            throw ValidationException::withMessages(['two_factor_token' => __('identity::two_factor.errors.expired')]);
        }
        if (! TwoFactor::check($user, $code)) {
            $this->pending->missed($token);
            throw ValidationException::withMessages(['code' => __('identity::two_factor.errors.wrong')]);
        }
        $this->pending->forget($token);

        return $this->tokens->issue($user, $deviceName ?: $step['device']);
    }
}
