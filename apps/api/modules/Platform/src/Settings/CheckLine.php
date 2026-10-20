<?php

namespace Modules\Platform\Settings;

/**
 * One thing "Try it" found: about which service (if any), how it went (ok,
 * warn, fail, or info for a plain fact), and what it says and what to do as
 * wording keys with their values, so a kept result reads in whoever opens
 * the page's language.
 */
final readonly class CheckLine
{
    public const LEVELS = ['info' => 0, 'ok' => 0, 'warn' => 1, 'fail' => 2];

    /** @param  array<string, string|int>  $with */
    public function __construct(
        public ?string $service,
        public string $level,
        public string $says,
        public array $with = [],
        public ?string $todo = null,
        public string $code = '',
    ) {}

    public function text(): string
    {
        $text = __($this->says, $this->values());

        return $this->code !== '' ? $text.' ('.$this->code.')' : $text;
    }

    public function todoText(): ?string
    {
        return $this->todo === null ? null : __($this->todo, $this->values());
    }

    /** The line for a page: its words escaped, and names, values and the provider's code each kept in their own direction. */
    public function html(): string
    {
        return self::isolated($this->says, $this->values()).($this->code !== '' ? ' <bdi dir="ltr">('.e($this->code).')</bdi>' : '');
    }

    public function todoHtml(): ?string
    {
        return $this->todo === null ? null : self::isolated($this->todo, $this->values());
    }

    /** @return array<string, string|int> */
    private function values(): array
    {
        return $this->with + ($this->service !== null ? ['service' => __('platform::services.names.'.$this->service)] : []);
    }

    /** @param  array<string, string|int>  $with */
    private static function isolated(string $key, array $with): string
    {
        $marks = array_map(fn (string $name) => "\u{E000}{$name}\u{E001}", array_combine(array_keys($with), array_keys($with)));
        $html = e(__($key, $marks));
        foreach ($with as $name => $value) {
            $html = str_replace("\u{E000}{$name}\u{E001}", '<bdi>'.e((string) $value).'</bdi>', $html);
        }

        return $html;
    }

    /** @return array{service: ?string, level: string, says: string, with: array<string, string|int>, todo: ?string, code: string} */
    public function toArray(): array
    {
        return ['service' => $this->service, 'level' => $this->level, 'says' => $this->says, 'with' => $this->with, 'todo' => $this->todo, 'code' => $this->code];
    }

    /** @param  array<string, mixed>  $line */
    public static function from(array $line): self
    {
        return new self($line['service'] ?? null, (string) ($line['level'] ?? 'info'), (string) ($line['says'] ?? ''), (array) ($line['with'] ?? []), $line['todo'] ?? null, (string) ($line['code'] ?? ''));
    }
}
