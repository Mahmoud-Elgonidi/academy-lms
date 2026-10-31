<?php

namespace Modules\Quran\Queries\Hifz;

use Carbon\CarbonImmutable;
use Modules\Quran\Queries\Mushaf\MistakesBook;
use Modules\SharedKernel\Time\Clock;

/**
 * One learner's hifz as the course's teacher and the academy's supervisors
 * read it: the map, the last two weeks' reviews (in the reader's words and
 * clock) and the mistakes notebook.
 */
final class StudentHifz
{
    public function __construct(private readonly HifzMap $map, private readonly MistakesBook $book) {}

    /**
     * @return array{map: array<string, mixed>, recent: list<array{page: int, grade: string, source: string, at: string}>, book: array<string, mixed>, tz: string}
     */
    public function for(string $studentId, CarbonImmutable $now, string $tz, int $recent = 12): array
    {
        return [
            'map' => $this->map->for($studentId, $now),
            'recent' => array_map(fn (array $r) => [
                'page' => $r['page'],
                'grade' => __('quran::hifz.grades.'.$r['grade']),
                'source' => __('quran::hifz.sources.'.$r['source']),
                'at' => $r['reviewed_at']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('D j M · H:i')),
            ], $this->map->recent($studentId, $now, $recent)),
            'book' => $this->book->for($studentId, $now),
            'tz' => $tz,
        ];
    }
}
