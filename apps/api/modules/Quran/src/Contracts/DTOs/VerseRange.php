<?php

namespace Modules\Quran\Contracts\DTOs;

/**
 * A passage of the Quran by numbers: a surah and its verses from, to. Kept as
 * {kind: quran, surah, from, to} where another module holds it for the Quran
 * module (homework.expected). Its words always come from the platform's copy
 * (Texts\QuranText), never from what anyone typed or a model wrote.
 */
final readonly class VerseRange
{
    /** A passage's verses at most: a long surah is set as several lessons. */
    public const VERSES_MAX = 300;

    private function __construct(
        public int $surah,
        public int $from,
        public int $to,
    ) {}

    public static function of(int $surah, int $from, int $to): self
    {
        return new self($surah, $from, $to);
    }

    /** @param  ?array<string, mixed>  $data  as kept; null or anything else is none */
    public static function fromArray(?array $data): ?self
    {
        return ($data['kind'] ?? null) === 'quran' && isset($data['surah'], $data['from'], $data['to'])
            ? self::of((int) $data['surah'], (int) $data['from'], (int) $data['to'])
            : null;
    }

    /** @return array{kind: string, surah: int, from: int, to: int} */
    public function toArray(): array
    {
        return ['kind' => 'quran', 'surah' => $this->surah, 'from' => $this->from, 'to' => $this->to];
    }
}
