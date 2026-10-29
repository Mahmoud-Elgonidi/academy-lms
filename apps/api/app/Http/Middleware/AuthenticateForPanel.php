<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's way in, with one change. The panels share one session on the
 * academy's address, so someone signed in to /teach or /my who opens /admin
 * (or the other way round) is not let in: instead of a bare 403 they read who
 * they are signed in as, with a way to sign out (this panel's logout stays open
 * to them, back to its sign-in) and one to their own area when they have one.
 */
final class AuthenticateForPanel extends Authenticate
{
    /** Where each panel opens, in the order a person's own area is looked for. */
    private const PANELS = ['academy', 'teach', 'family'];

    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();
        $panel = Filament::getCurrentOrDefaultPanel();
        $user = $guard->user();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
            if ($request->route()?->getName() === 'filament.'.$panel->getId().'.auth.logout') {
                $this->auth->shouldUse(Filament::getAuthGuard());

                return;
            }

            // The panel sets the language after this step: the page reads in the account's own.
            if (in_array($locale = (string) data_get($user, 'locale'), ['ar', 'en'], true)) {
                app()->setLocale($locale);
            }

            $own = collect(self::PANELS)
                ->reject(fn (string $id) => $id === $panel->getId())
                ->map(fn (string $id) => Filament::getPanel($id))
                ->first(fn ($other) => $user->canAccessPanel($other));

            throw new HttpResponseException(response()->view('shared::panels.wrong-account', [
                'name' => (string) ($user->name ?? ''),
                'area' => __('shared::panels.areas.'.$panel->getId()),
                'logout' => route('filament.'.$panel->getId().'.auth.logout'),
                'own' => $own?->getUrl(),
                'ownArea' => $own === null ? null : __('shared::panels.areas.'.$own->getId()),
            ], 403));
        }

        parent::authenticate($request, $guards);
    }
}
