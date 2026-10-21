<?php

namespace Modules\Curriculum\Contracts\DTOs;

/**
 * Where a lesson's text comes from: words the academy typed (up to
 * TEXT_MAX characters), or a passage of the Quran by surah and verses,
 * whose words always come from the platform's copy (Texts\QuranText),
 * never from what anyone typed or a model wrote. Kept on the lesson as
 * curriculum_nodes.text_source.
 */
final readonly class TextSource
{
    public const TEXT_MAX = 5000;

    /** A passage's verses at most: a long surah is set as several lessons. */
    public const VERSES_MAX = 300;

    private function __construct(
        public string $kind,
        public ?string $text = null,
        public ?int $surah = null,
        public ?int $from = null,
        public ?int $to = null,
    ) {}

    public static function text(string $text): self
    {
        return new self('text', $text);
    }

    public static function quran(int $surah, int $from, int $to): self
    {
        return new self('quran', null, $surah, $from, $to);
    }

    /** @param  ?array<string, mixed>  $data  as kept; null or anything else is no text */
    public static function fromArray(?array $data): ?self
    {
        return match ($data['kind'] ?? null) {
            'text' => is_string($data['text'] ?? null) && trim($data['text']) !== '' ? self::text($data['text']) : null,
            'quran' => isset($data['surah'], $data['from'], $data['to']) ? self::quran((int) $data['surah'], (int) $data['from'], (int) $data['to']) : null,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->kind === 'quran'
            ? ['kind' => 'quran', 'surah' => $this->surah, 'from' => $this->from, 'to' => $this->to]
            : ['kind' => 'text', 'text' => $this->text];
    }

    public function isQuran(): bool
    {
        return $this->kind === 'quran';
    }
}
