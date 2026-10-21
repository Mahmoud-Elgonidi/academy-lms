<?php

namespace Modules\Platform\Settings;

/** What "Try it" found for a section: its lines, the worst of them, and whether it tried what was typed rather than what is saved. */
final readonly class CheckResult
{
    /** @param  list<CheckLine>  $lines */
    public function __construct(
        public array $lines,
        public bool $typed = false,
    ) {}

    /** info (only lines that tell, or none), ok, warn or fail: the worst line. */
    public function level(): string
    {
        // Lines that only tell (a service off, nothing set up) are not "it works".
        $worst = 'info';
        foreach ($this->lines as $line) {
            if (CheckLine::LEVELS[$line->level] > CheckLine::LEVELS[$worst] || ($worst === 'info' && $line->level === 'ok')) {
                $worst = $line->level;
            }
        }

        return $worst;
    }

    public function ok(): bool
    {
        return $this->level() !== 'fail';
    }

    /** Every line in words, one after the other. */
    public function says(): string
    {
        return implode(' ', array_map(fn (CheckLine $line) => $line->text(), $this->lines));
    }

    /** @return array{lines: list<array<string, mixed>>, typed: bool} */
    public function toArray(): array
    {
        return ['lines' => array_map(fn (CheckLine $line) => $line->toArray(), $this->lines), 'typed' => $this->typed];
    }

    /** @param  array<string, mixed>  $kept */
    public static function from(array $kept): self
    {
        return new self(array_map(fn (array $line) => CheckLine::from($line), (array) ($kept['lines'] ?? [])), (bool) ($kept['typed'] ?? false));
    }
}
