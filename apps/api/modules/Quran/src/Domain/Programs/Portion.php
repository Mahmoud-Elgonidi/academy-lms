<?php

namespace Modules\Quran\Domain\Programs;

use Modules\Quran\Contracts\DTOs\VerseRange;

/**
 * A lesson's portion (LessonPortion): what is new (from where the learner
 * is), the near review (the portions just before it) and the far review
 * (pages cycling over what they have memorized), each as passages and the
 * pages they are on; where the learner will be once the new part is
 * memorized (null: the program's scope is done with it); the exam stops it
 * completes (a surah, a part) and the pages it completes; on the Noorani
 * Qaida, the lesson's number.
 */
final readonly class Portion
{
    /**
     * @param  list<VerseRange>  $new
     * @param  list<VerseRange>  $near
     * @param  list<VerseRange>  $far
     * @param  list<int>  $newPages
     * @param  list<int>  $nearPages
     * @param  list<int>  $farPages
     * @param  list<array{kind: string, number: int}>  $stops
     * @param  list<int>  $completedPages  the new part's pages whose last verse to be taught is in it (memorized once it passes)
     */
    public function __construct(
        public array $new = [],
        public array $near = [],
        public array $far = [],
        public array $newPages = [],
        public array $nearPages = [],
        public array $farPages = [],
        public ?Position $next = null,
        public bool $finished = false,
        public ?int $lesson = null,
        public array $stops = [],
        public float $progress = 0.0,
        public array $completedPages = [],
    ) {}

    /** @return list<VerseRange> */
    public function part(string $part): array
    {
        return match ($part) {
            'new' => $this->new,
            'near' => $this->near,
            'far' => $this->far,
            default => [],
        };
    }

    /** @return list<int> */
    public function pagesOf(string $part): array
    {
        return match ($part) {
            'new' => $this->newPages,
            'near' => $this->nearPages,
            'far' => $this->farPages,
            default => [],
        };
    }

    /** Whether the part has anything in it (the new part of a Noorani lesson is its lesson). */
    public function has(string $part): bool
    {
        return $part === 'new' ? ($this->new !== [] || $this->lesson !== null) : $this->part($part) !== [];
    }
}
