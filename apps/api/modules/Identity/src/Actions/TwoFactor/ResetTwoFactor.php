<?php

namespace Modules\Identity\Actions\TwoFactor;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Impersonation\StartImpersonation;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * The platform team clears someone's two-step sign-in, for a person who lost
 * their phone and their recovery codes: with a reason, in both activity
 * logs' reach (the academy's, by the platform team). They set it up again at
 * their next sign-in (the owner, admins and accountants must).
 */
final class ResetTwoFactor
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(TenantRef $academy, string $userId, PlatformAdminRef $admin, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < StartImpersonation::REASON_MIN) {
            throw ValidationException::withMessages(['reason' => __('identity::two_factor.errors.reason', ['min' => StartImpersonation::REASON_MIN])]);
        }

        $this->tenants->run($academy, function () use ($userId, $admin, $reason) {
            $user = User::query()->find($userId);
            if ($user === null || ! TwoFactor::enabledFor($user)) {
                throw ValidationException::withMessages(['user' => __('identity::two_factor.errors.not_on')]);
            }
            // Straight to the row: this is the platform team's own step, logged once below as such.
            User::query()->whereKey($user->id)->update(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null]);

            app(AuditTrail::class)->record('identity.two_factor_reset', 'user', $user->id, ['name' => $user->name, 'reason' => $reason],
                new AuditActor('platform', $admin->id, $admin->name));
        });
    }
}
