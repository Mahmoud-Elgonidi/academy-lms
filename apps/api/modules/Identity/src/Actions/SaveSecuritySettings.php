<?php

namespace Modules\Identity\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\IdentitySettings;
use Modules\Identity\TwoFactor\AcademyTwoFactorRule;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The owner decides whether two-step sign-in is required of the owner, the
 * admins and the accountants (Settings › Security). Off, each of them may
 * still turn it on for themselves in My settings, and whoever has it on is
 * still asked for it. Never while the platform team is signed in as someone.
 * Audited (required or optional).
 */
final class SaveSecuritySettings
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly AcademyTwoFactorRule $rule,
    ) {}

    public function handle(bool $requireTwoFactor): IdentitySettings
    {
        if (! Gate::allows(Ability::ManageSecurity->value)) {
            throw new AuthorizationException;
        }
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['require_two_factor' => __('identity::security.errors.impersonating')]);
        }

        $settings = DB::transaction(function () use ($requireTwoFactor) {
            $settings = IdentitySettings::query()->lockForUpdate()->first() ?? new IdentitySettings(['require_two_factor' => true]);
            if ($settings->exists && $settings->require_two_factor === $requireTwoFactor) {
                return $settings;
            }
            $settings->fill(['require_two_factor' => $requireTwoFactor])->save();
            $this->audit->record($requireTwoFactor ? 'identity.two_factor_required' : 'identity.two_factor_optional', 'identity_settings');

            return $settings;
        });
        $this->rule->forget();

        return $settings;
    }
}
