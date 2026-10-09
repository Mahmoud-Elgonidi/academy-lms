<?php

namespace Modules\Identity\TwoFactor;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;

/**
 * Filament's authenticator app, for every panel and the console. Someone
 * with no recovery codes (all used up, or never given one: the demo
 * console's admin) who types one in is told it is wrong; Filament itself
 * throws there, and the sign-in page answered 500.
 */
final class AuthenticatorApp extends AppAuthentication
{
    /** @return array<string> */
    public function getRecoveryCodes(HasAppAuthenticationRecovery $user): array
    {
        return $user->getAppAuthenticationRecoveryCodes() ?? [];
    }
}
