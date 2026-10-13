<?php

namespace Modules\Identity\Actions;

use Carbon\CarbonImmutable;
use Modules\Identity\Contracts\SignInGate;
use Modules\Identity\Models\User;
use Modules\Identity\SignIn\LastSignIn;
use Modules\Identity\TwoFactor\PendingSignIns;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\SharedKernel\Http\ProblemException;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * A Sanctum token for an app on one device (password, code, or a discovered
 * account). Whoever has two-step sign-in on gets, instead, a problem of type
 * "two-factor-required" with a token for the second step (auth/two-factor);
 * the owner, admins and accountants who have not set it up yet get
 * "two-factor-setup-required" (they set it up in the back office first).
 * Someone the sign-in gate holds back (a child waiting for a guardian's
 * consent) gets its problem before any of that.
 */
final class IssueAccessToken
{
    public function __construct(
        private readonly PendingSignIns $pending,
        private readonly SignInGate $gate,
    ) {}

    /** @return array{user: User, token: string, expires_at: ?CarbonImmutable} */
    public function handle(User $user, string $deviceName): array
    {
        if ($refusal = $this->gate->refusal($user->id)) {
            throw new ProblemException(403, $refusal->type, $refusal->message);
        }
        if (TwoFactor::enabledFor($user)) {
            $step = $this->pending->begin($user, $deviceName);
            throw new ProblemException(403, 'two-factor-required', __('identity::two_factor.api.required'), [
                'two_factor_token' => $step['token'],
                'expires_at' => app(LocalTimePresenter::class)->present($step['expires_at']),
            ]);
        }
        if (TwoFactor::requiredFor($user)) {
            throw new ProblemException(403, 'two-factor-setup-required', __('identity::two_factor.api.setup_required'));
        }

        return $this->issue($user, $deviceName);
    }

    /**
     * The token itself, once every step is done (CompleteTwoFactorSignIn).
     *
     * @return array{user: User, token: string, expires_at: ?CarbonImmutable}
     */
    public function issue(User $user, string $deviceName): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->toImmutable()->addMinutes((int) $minutes) : null;

        $token = $user->createToken($deviceName, ['*'], $expiresAt);
        LastSignIn::stamp($user->id);

        return ['user' => $user->load('roleRecords'), 'token' => $token->plainTextToken, 'expires_at' => $expiresAt];
    }
}
