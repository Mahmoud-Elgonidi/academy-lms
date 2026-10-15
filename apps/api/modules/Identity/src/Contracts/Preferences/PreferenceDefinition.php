<?php

namespace Modules\Identity\Contracts\Preferences;

use Illuminate\Support\Facades\Gate;
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
     * @param  list<string>  $abilities  shown only to someone with one of these (none: everyone)
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $options,
        public string $default,
        public ?string $hint = null,
        public array $abilities = [],
    ) {
        if (! array_key_exists($default, $options)) {
            throw new InvalidArgumentException("Default [{$default}] is not an option of [{$key}].");
        }
    }

    /** Whether the signed-in person is offered this choice (a guardian is not asked how the live board looks). */
    public function offered(): bool
    {
        return $this->abilities === [] || Gate::any($this->abilities);
    }

    public function allows(mixed $value): bool
    {
        return is_string($value) && array_key_exists($value, $this->options);
    }
}
