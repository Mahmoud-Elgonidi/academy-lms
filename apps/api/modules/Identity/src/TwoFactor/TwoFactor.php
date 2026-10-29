<?php

namespace Modules\Identity\TwoFactor;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Signing in with a second factor: an authenticator app (a code that
 * changes every 30 seconds) with one-time recovery codes. Required of the
 * owner, the admins and the accountants (they hold the money, the gateway
 * keys and every family's data) unless the academy's owner turned that off
 * (identity_settings.require_two_factor, SaveSecuritySettings); anyone else
 * may turn it on. Whoever has it
 * on is asked for it on every way in: the panels (by password or by a code
 * sent to them), the portal and the apps.
 */
final class TwoFactor
{
    /** @var list<Role> */
    public const REQUIRED_FOR = [Role::Owner, Role::Admin, Role::Accountant];

    /** One of the roles it is asked of, in an academy that requires it. */
    public static function requiredFor(User $user): bool
    {
        return $user->hasRole(...self::REQUIRED_FOR) && app(AcademyTwoFactorRule::class)->required();
    }

    public static function enabledFor(User $user): bool
    {
        return filled($user->getAppAuthenticationSecret());
    }

    /** The authenticator app as the panels and the API use it: the academy's name in the app, recovery codes on. */
    public static function provider(): AppAuthentication
    {
        $academy = app(TenantContext::class)->current();
        $name = $academy === null ? null : (app()->getLocale() === 'ar' ? $academy->nameAr : $academy->nameEn);

        return AuthenticatorApp::make()->recoverable()->brandName($name ?: config('app.name'));
    }

    /** Whether this code from the person's authenticator app, or one of their recovery codes (used up), is right. */
    public static function check(User $user, string $code): bool
    {
        $code = trim($code);
        if (! self::enabledFor($user) || $code === '') {
            return false;
        }
        $provider = self::provider();

        if (preg_match('/^\d{6}$/', $code) === 1) {
            return $provider->verifyCode($code, $user->getAppAuthenticationSecret(), shouldPreventCodeReuse: true);
        }

        return ($user->getAppAuthenticationRecoveryCodes() ?? []) !== [] && $provider->verifyRecoveryCode($code, $user);
    }
}
