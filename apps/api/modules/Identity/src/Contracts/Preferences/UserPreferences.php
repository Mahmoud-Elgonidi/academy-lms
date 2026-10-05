<?php

namespace Modules\Identity\Contracts\Preferences;

/**
 * Per-user preferences. Modules declare the choices they offer in their
 * service provider's boot(); users change them on the screen that uses them
 * or on "My settings", and both paths go through the same Action.
 */
interface UserPreferences
{
    public function define(PreferenceDefinition $definition): void;

    /** @return array<string, PreferenceDefinition> */
    public function definitions(): array;

    /** The user's value, or the declared default. */
    public function get(string $userId, string $key): string;

    public function set(string $userId, string $key, string $value): void;
}
