<?php

namespace Modules\Quran\Domain\Programs;

/**
 * A Quran program as a lesson's portion reads it (ProgramRules has checked
 * it): its track, its scope (from a surah's verse to another's, in the
 * mushaf's order; on the Noorani Qaida, from a lesson to another), which way
 * it is learnt, the unit and the new amount a lesson, the near review (the
 * last N portions before where the learner is), the far review (pages a
 * lesson, cycling over what they have memorized), the grade a new portion
 * needs to count as memorized, and where exams fall.
 */
final readonly class ProgramShape
{
    public function __construct(
        public string $track,
        public ?int $fromSurah,
        public int $fromVerse,
        public ?int $toSurah,
        public int $toVerse,
        public string $direction = 'forward',
        public string $unit = 'verses',
        public float $newAmount = 1.0,
        public int $nearPortions = 0,
        public int $farPages = 0,
        public string $passGrade = 'good',
        public string $examStops = 'none',
    ) {}

    /** @param  array<string, mixed>  $program  a program's fields as kept */
    public static function fromArray(array $program): self
    {
        return new self(
            track: (string) $program['track'],
            fromSurah: isset($program['scope_from_surah']) ? (int) $program['scope_from_surah'] : null,
            fromVerse: (int) $program['scope_from_verse'],
            toSurah: isset($program['scope_to_surah']) ? (int) $program['scope_to_surah'] : null,
            toVerse: (int) $program['scope_to_verse'],
            direction: (string) ($program['direction'] ?? 'forward'),
            unit: (string) $program['unit'],
            newAmount: (float) $program['new_amount'],
            nearPortions: (int) ($program['near_portions'] ?? 0),
            farPages: (int) ($program['far_pages'] ?? 0),
            passGrade: (string) ($program['pass_grade'] ?? 'good'),
            examStops: (string) ($program['exam_stops'] ?? 'none'),
        );
    }

    /** The same program at the learner's own pace (what a plan changes; null keeps the program's). */
    public function withPace(?float $newAmount, ?int $nearPortions, ?int $farPages): self
    {
        return new self($this->track, $this->fromSurah, $this->fromVerse, $this->toSurah, $this->toVerse, $this->direction, $this->unit,
            $newAmount ?? $this->newAmount, $nearPortions ?? $this->nearPortions, $farPages ?? $this->farPages, $this->passGrade, $this->examStops);
    }

    public function isNoorani(): bool
    {
        return $this->track === 'noorani';
    }

    /** Whether a lesson brings something new (review and consolidation brings none). */
    public function hasNew(): bool
    {
        return $this->newAmount > 0;
    }

    /** Memorizing (hifz and talqin) closes a surah in a lesson of its own once the portion is half done. */
    public function stopsAtSurahEnd(): bool
    {
        return in_array($this->track, ['hifz', 'talqin'], true);
    }

    /** Whether what is new counts as memorized pages (reading, tilawa and ijaza recite without memorizing anew). */
    public function memorizes(): bool
    {
        return in_array($this->track, ['hifz', 'talqin'], true);
    }
}
