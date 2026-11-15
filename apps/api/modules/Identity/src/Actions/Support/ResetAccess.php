<?php

namespace Modules\Identity\Actions\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Impersonation\StartImpersonation;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Models\User;
use Modules\Identity\Onboarding\AccessMail;
use Modules\Identity\Onboarding\AccessMailFailed;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;

/**
 * The platform team resets a back-office account's access, for someone who
 * forgot their password (and lost their second step): the password and
 * two-step sign-in are cleared, every session and token ends, and one email
 * goes at once to the account, copied to up to five addresses, with a fresh
 * link to choose a new password (`identity.welcome`); they set two-step
 * sign-in up again after. From the console (SupportSignIn, the admin) or the
 * server (`platform:welcome` with `reset`, the system). With a reason,
 * audited as `identity.access_reset` with whether two-step was on and the
 * number of copies, never an address. Only staff accounts, never a managed
 * minor, never while the platform team is signed in as someone.
 *
 * Mail is checked before anything changes, so a server that cannot send
 * leaves the account as it was. If the mailer then refuses, the account
 * stays reset and the refusal says so: send again with the same step.
 */
final class ResetAccess
{
    public const MAX_COPIES = 5;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly IdentityApi $identity,
        private readonly AccessMail $mail,
    ) {}

    /**
     * @param  list<string>  $cc
     * @return array{email: string, copies: int, had_two_factor: bool}
     *
     * @throws ValidationException
     */
    public function handle(TenantRef $academy, string $userId, ?PlatformAdminRef $admin, string $reason, array $cc = []): array
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['user' => __('identity::impersonation.errors.password')]);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < StartImpersonation::REASON_MIN) {
            throw ValidationException::withMessages(['reason' => __('identity::two_factor.errors.reason', ['min' => StartImpersonation::REASON_MIN])]);
        }
        $cc = $this->copies($cc);
        try {
            AccessMail::ensureReady();
        } catch (AccessMailFailed $e) {
            throw ValidationException::withMessages(['mail' => $e->getMessage()]);
        }

        [$user, $copies, $hadTwoFactor] = $this->tenants->run($academy, function () use ($userId, $admin, $reason, $cc) {
            $user = User::query()->with('roleRecords')->find($userId);
            if ($user === null || ! $user->isActive()) {
                throw ValidationException::withMessages(['user' => __('identity::onboarding.access.errors.not_found')]);
            }
            if ($user->is_managed_minor || StaffRules::staffOf($user->roles()) === []) {
                throw ValidationException::withMessages(['user' => __('identity::onboarding.access.errors.not_staff')]);
            }
            $email = mb_strtolower(trim((string) $user->email));
            if ($email === '') {
                throw ValidationException::withMessages(['user' => __('identity::onboarding.access.errors.no_email')]);
            }
            $copies = array_values(array_diff($cc, [$email]));
            $hadTwoFactor = TwoFactor::enabledFor($user);

            DB::transaction(function () use ($user, $admin, $reason, $copies, $hadTwoFactor) {
                // Straight to the row, as ResetTwoFactor does: the platform team's own step, logged once below as such.
                User::query()->whereKey($user->id)->update([
                    'password' => null,
                    'remember_token' => null,
                    'app_authentication_secret' => null,
                    'app_authentication_recovery_codes' => null,
                ]);
                $this->identity->endSessions($user->id);

                app(AuditTrail::class)->record('identity.access_reset', 'user', $user->id,
                    ['name' => $user->name, 'reason' => $reason, 'had_two_factor' => $hadTwoFactor, 'copies' => count($copies)],
                    $admin !== null ? new AuditActor('platform', $admin->id, $admin->name) : new AuditActor('system', null, null));
            });

            return [$user->refresh(), $copies, $hadTwoFactor];
        });

        try {
            $this->mail->send($academy, $user, $copies, AccessMail::RESET);
        } catch (AccessMailFailed $e) {
            throw ValidationException::withMessages(['mail' => __('identity::onboarding.access.errors.mail_after_reset', ['why' => $e->getMessage()])]);
        }

        return ['email' => (string) $user->email, 'copies' => count($copies), 'had_two_factor' => $hadTwoFactor];
    }

    /**
     * @param  list<mixed>  $cc
     * @return list<string> lower-cased, each a valid address, at most five, none twice
     */
    private function copies(array $cc): array
    {
        $cc = array_values(array_filter(array_map(fn ($c) => is_string($c) ? mb_strtolower(trim($c)) : $c, $cc), fn ($c) => $c !== ''));
        $v = Validator::make(['cc' => $cc], [
            'cc' => ['array', 'max:'.self::MAX_COPIES],
            'cc.*' => ['string', 'email', 'distinct'],
        ], [
            'cc.max' => __('identity::onboarding.access.errors.cc_many', ['max' => self::MAX_COPIES]),
            'cc.*.string' => __('identity::onboarding.access.errors.cc_bad'),
            'cc.*.email' => __('identity::onboarding.access.errors.cc_bad'),
            'cc.*.distinct' => __('identity::onboarding.access.errors.cc_twice'),
        ]);
        if ($v->fails()) {
            throw ValidationException::withMessages(['cc' => $v->errors()->first()]);
        }

        return $cc;
    }
}
