<?php

namespace Modules\Import\Support;

/**
 * A column an importer understands: its key, whether a row needs it, and the
 * titles it goes by in academies' sheets (Arabic and English), so most files
 * map themselves.
 */
final readonly class Field
{
    /** @param  list<string>  $aliases */
    public function __construct(
        public string $key,
        public bool $required,
        public array $aliases,
    ) {}

    public function matches(string $header): bool
    {
        $h = Text::key($header);

        return $h !== '' && in_array($h, array_map(fn (string $a) => Text::key($a), [$this->key, ...$this->aliases]), true);
    }
}
