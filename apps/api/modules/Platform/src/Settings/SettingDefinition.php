<?php

namespace Modules\Platform\Settings;

/**
 * One of the platform's settings an owner sets in the console: its group on
 * the screen, the config paths it fills (a key may serve two parts, as the
 * Anthropic key does the helpers and the games designer), the .env line it
 * stands in for, and how a value is checked and read.
 *
 * Types: secret and json_file are never shown again once saved; select and
 * toggle offer fixed values; text, url, email, int and list are typed.
 */
final readonly class SettingDefinition
{
    /**
     * @param  list<string>  $paths
     * @param  list<string>  $options  a select's values
     * @param  list<mixed>  $rules  more checks on a value given (a list's: on each of its items)
     * @param  array<string, string>  $map  a select's value => what the config holds for it (when they differ)
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $type,
        public array $paths,
        public ?string $env = null,
        public array $options = [],
        public array $rules = [],
        public string $cast = 'string',
        public array $map = [],
    ) {}

    public function secret(): bool
    {
        return in_array($this->type, ['secret', 'json_file'], true);
    }

    /** The stored text as the config holds it. */
    public function toConfig(string $stored): mixed
    {
        if ($this->map !== []) {
            return $this->map[$stored] ?? $stored;
        }

        return match ($this->cast) {
            'int' => (int) $stored,
            'bool' => $stored === '1',
            'array' => self::list($stored),
            default => $stored,
        };
    }

    /** The select's value that stands for what the config holds (the server's own value, shown). */
    public function fromConfig(mixed $value): mixed
    {
        $option = array_search($value, $this->map, true);

        return $option === false ? $value : $option;
    }

    /** @return list<string> */
    public static function list(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,\s]+/u', $value) ?: []), fn ($v) => $v !== ''));
    }
}
