<?php

namespace Modules\Identity\Actions\Otp;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Models\User;
use Modules\Identity\Otp\Challenges;
use Modules\SharedKernel\Tenancy\TenantContext;

/** The code is right: the account it was sent for, still active. The caller signs it in (a token, or the panel's session). */
final class VerifySignInCode
{
    public function __construct(private readonly Challenges $challenges, private readonly TenantContext $tenants) {}

    public function handle(string $challengeId, string $code): User
    {
        $challenge = $this->challenges->verify($challengeId, $code, OtpPurpose::SignIn, $this->tenants->require()->id);

        $user = User::query()->find($challenge->user_id);

        return $user !== null && $user->isActive()
            ? $user
            : throw ValidationException::withMessages(['code' => __('identity::otp.expired')]);
    }
}
