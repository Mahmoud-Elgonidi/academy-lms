<?php

namespace Modules\Identity\Contracts\Preferences;

use InvalidArgumentException;

/**
 * A per-user choice a module offers, shown on "My settings" and readable
 * anywhere through UserPreferences.
 *
 * Labels are translation keys, so each module keeps its own strings.
 */
final readonly class PreferenceDefinition
{
    /**
     * @param  string  $key  dotted, owned by one module: "live_board.layout"
     * @param  array<string, string>  $options  value => label translation key
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $options,
        public string $default,
        public ?string $hint = null,
    ) {
        if (! array_key_exists($default, $options)) {
            throw new InvalidArgumentException("Default [{$default}] is not an option of [{$key}].");
        }
    }

    public function allows(mixed $value): bool
    {
        return is_string($value) && array_key_exists($value, $this->options);
    }
}
