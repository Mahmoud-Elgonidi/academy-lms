<?php

namespace Modules\Identity\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\Identity\Preferences\PreferenceStore;

/** The signed-in user changes per-user screen choices (only declared keys and options). */
final class UpdatePreferences
{
    public function __construct(private readonly PreferenceStore $store) {}

    /** @param  array<string, mixed>  $changes */
    public function handle(User $user, array $changes): User
    {
        $definitions = $this->store->definitions();
        $errors = [];

        foreach ($changes as $key => $value) {
            if (! isset($definitions[$key]) || ! $definitions[$key]->allows($value)) {
                $errors["preferences.{$key}"] = [__('identity::users.preference_invalid')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $user->preferences = array_merge($user->preferences ?? [], $changes);
        $user->save();

        return $user;
    }
}
