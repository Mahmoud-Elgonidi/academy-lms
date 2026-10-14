<?php

namespace App\Http\Middleware;

use App\Filament\Academy\StartHere;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\SharedKernel\Authorization\Ability;
use Symfony\Component\HttpFoundation\Response;

/**
 * The back office opens on the live board; staff who do not watch it (sales,
 * the accountant: revenue) land on their own first screen instead of a refusal.
 * While the academy is not ready for its first families (a required step of
 * "Start here" left), its owner and admins land on "Start here" the first
 * time they open the home after signing in (and after setting up two-step
 * sign-in, when they must); the live board stays a click away.
 */
final class OpenFirstAllowedScreen
{
    /** The session's mark that its next visit to the home is the landing after a sign-in. */
    public const LANDING = 'lms.landing';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->route()?->getName() === 'filament.academy.pages.live' && $request->user() !== null) {
            // Setting up two-step sign-in comes first (the panel's own middleware sends them there); the landing waits for it.
            $landing = $request->hasSession() && ! self::twoFactorFirst() && $request->session()->pull(self::LANDING, false) === true;

            if (! Gate::allows(Ability::ViewLiveBoard->value)) {
                $route = match (true) {
                    Gate::allows(Ability::ManageLeads->value) => 'filament.academy.pages.leads',
                    Gate::allows(Ability::ViewBilling->value) => 'filament.academy.pages.revenue',
                    Gate::allows(Ability::FindTeachers->value) => 'filament.academy.pages.find-teacher',
                    default => null,
                };
                if ($route !== null) {
                    return redirect()->route($route);
                }
            } elseif ($landing && Gate::allows(Ability::SetUpAcademy->value) && ! StartHere::progress()->complete()) {
                return redirect()->route('filament.academy.pages.start-here');
            }
        }

        return $next($request);
    }

    /** Whether the panel asks for two-step sign-in and the person has no way of it on yet. */
    private static function twoFactorFirst(): bool
    {
        $panel = Filament::getCurrentPanel();
        $user = Filament::auth()->user();
        if ($panel === null || $user === null || ! $panel->isMultiFactorAuthenticationRequired()) {
            return false;
        }

        return collect($panel->getMultiFactorAuthenticationProviders())->every(fn ($provider) => ! $provider->isEnabled($user));
    }

    /** A sign-in on the panels (the password, a code, Google or Apple, a welcome link): the next visit to the home lands. */
    public static function signedIn(Login $event): void
    {
        if ($event->guard === 'web') {
            session()->put(self::LANDING, true);
        }
    }
}
