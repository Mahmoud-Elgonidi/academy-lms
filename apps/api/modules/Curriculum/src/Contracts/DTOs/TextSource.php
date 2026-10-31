<?php

namespace Modules\Curriculum\Contracts\DTOs;

/**
 * A lesson's text: words the academy typed (up to TEXT_MAX characters),
 * kept on the lesson as curriculum_nodes.text_source. What the practice
 * helper reads.
 */
final readonly class TextSource
{
    public const TEXT_MAX = 5000;

    private function __construct(
        public string $kind,
        public string $text,
    ) {}

    public static function text(string $text): self
    {
        return new self('text', $text);
    }

    /** @param  ?array<string, mixed>  $data  as kept; null or anything else is no text */
    public static function fromArray(?array $data): ?self
    {
        return ($data['kind'] ?? null) === 'text' && is_string($data['text'] ?? null) && trim($data['text']) !== '' ? self::text($data['text']) : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['kind' => 'text', 'text' => $this->text];
    }
}
