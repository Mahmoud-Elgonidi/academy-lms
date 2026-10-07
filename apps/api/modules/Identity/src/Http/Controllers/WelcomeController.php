<?php

namespace Modules\Identity\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Identity\Actions\SetFirstPassword;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\BrandingApi;

/**
 * Someone's own welcome link (from the message sent when their account was
 * made): they choose a password and land signed in on their own area. The
 * link is signed for them; once a password is set it only points to sign-in.
 */
final class WelcomeController
{
    public const LINK_DAYS = 14;

    public function show(Request $request, string $user, BrandingApi $branding): View
    {
        $person = $this->person($user);

        return view('identity::welcome', [
            'person' => $person,
            'ready' => $person->password === null,
            'login' => $person->email ?? $person->phone_e164 ?? $person->username,
            'action' => URL::temporarySignedRoute('identity.welcome.store', now()->addMinutes(30), ['user' => $person->id], absolute: false),
            'home' => self::home($person),
            'branding' => $branding->current(),
        ]);
    }

    public function store(Request $request, string $user, SetFirstPassword $set): RedirectResponse
    {
        $person = $this->person($user);
        if ($request->input('password') !== $request->input('password_confirmation')) {
            throw ValidationException::withMessages(['password' => __('identity::welcome.errors.confirm')]);
        }
        $set->handle($person, (string) $request->input('password'));

        Auth::guard('web')->login($person);
        $request->session()->regenerate();

        return redirect()->to(self::home($person));
    }

    private function person(string $id): User
    {
        $person = User::query()->find($id);
        abort_if($person === null || ! $person->isActive(), 404);
        app()->setLocale($person->locale ?: app()->getLocale());

        return $person;
    }

    /** Where they work: the back office for staff, the teacher area, or the family area. */
    public static function home(User $user): string
    {
        $roles = $user->roles();

        return match (true) {
            collect($roles)->contains(fn (Role $r) => $r->isStaff()) => Filament::getPanel('academy')->getUrl(),
            in_array(Role::Teacher, $roles, true) => Filament::getPanel('teach')->getUrl(),
            default => Filament::getPanel('family')->getUrl(),
        };
    }
}
