<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Modules\Identity\Filament\Pages\ChoosePassword;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\SharedKernel\Audit\AuditActor;
use Symfony\Component\HttpFoundation\Response;

/**
 * The back office asks the owner, the admins and the accountants to set up
 * their authenticator app before anything else (Filament's set-up page),
 * while their academy requires it (TwoFactor::requiredFor); everyone else
 * goes on, with or without one. The set-up asks for the password, so someone
 * with none chooses one first (ChoosePassword). The platform team signed in
 * as someone is not asked: they came through their own second factor.
 */
final class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! TwoFactor::requiredFor($user) || TwoFactor::enabledFor($user) || AuditActor::current()->onBehalfOf !== null) {
            return $next($request);
        }

        if ($user->password === null) {
            return redirect()->guest(ChoosePassword::getUrl());
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
