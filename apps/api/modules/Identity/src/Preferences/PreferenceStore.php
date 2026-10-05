<?php

namespace Modules\Identity\Preferences;

use InvalidArgumentException;
use Modules\Identity\Actions\UpdatePreferences;
use Modules\Identity\Contracts\Preferences\PreferenceDefinition;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Identity\Models\User;

final class PreferenceStore implements UserPreferences
{
    /** @var array<string, PreferenceDefinition> */
    private array $definitions = [];

    public function define(PreferenceDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function definition(string $key): PreferenceDefinition
    {
        return $this->definitions[$key] ?? throw new InvalidArgumentException("Unknown preference [{$key}].");
    }

    public function get(string $userId, string $key): string
    {
        return $this->valueFor(User::query()->findOrFail($userId), $key);
    }

    public function valueFor(User $user, string $key): string
    {
        $definition = $this->definition($key);
        $value = $user->preferences[$key] ?? null;

        // A stored value that is no longer offered falls back to the default.
        return $definition->allows($value) ? $value : $definition->default;
    }

    public function set(string $userId, string $key, string $value): void
    {
        app(UpdatePreferences::class)->handle(User::query()->findOrFail($userId), [$key => $value]);
    }
}
