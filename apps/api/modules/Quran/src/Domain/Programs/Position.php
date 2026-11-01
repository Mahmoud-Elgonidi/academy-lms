<?php

namespace Modules\Quran\Domain\Programs;

/**
 * Where a learner has reached in a Quran program: the verse their next new
 * portion starts at (a surah and a verse), or, on the Noorani Qaida, the
 * number of their next lesson (no surah).
 */
final readonly class Position
{
    public function __construct(
        public ?int $surah,
        public int $verse,
    ) {}

    public static function verse(int $surah, int $verse): self
    {
        return new self($surah, $verse);
    }

    public static function lesson(int $number): self
    {
        return new self(null, $number);
    }

    public function isLesson(): bool
    {
        return $this->surah === null;
    }

    /** @return array{surah: ?int, verse: int} */
    public function toArray(): array
    {
        return ['surah' => $this->surah, 'verse' => $this->verse];
    }

    public function equals(?self $other): bool
    {
        return $other !== null && $other->surah === $this->surah && $other->verse === $this->verse;
    }
}
