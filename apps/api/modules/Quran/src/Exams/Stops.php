<?php

namespace Modules\Quran\Exams;

use InvalidArgumentException;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Domain\Grading\Grading;
use Modules\Quran\Domain\Programs\LessonPortion;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Programs\Mushaf;

/**
 * An exam's stop as people read it and as it is graded (docs/architecture/02,
 * section 26): the end of a part or a surah, the whole program, or the
 * whole Quran (khatma), named in the reader's language from the platform's
 * surah names (never the Quran's words), and how many pages it covers (a
 * recitation's mistakes are weighed over its pages, Domain\Grading).
 */
final class Stops
{
    /** @var ?array<int, array{number: int, name_ar: string, name_en: string, verses: int}> */
    private ?array $surahs = null;

    public function __construct(
        private readonly QuranApi $quran,
        private readonly Mushaf $mushaf,
    ) {}

    /** The program's completion: khatma when its scope is the whole Quran. */
    public function completion(QuranProgram $program): string
    {
        $verses = $this->mushaf->surahVerses();

        return $program->track !== 'noorani' && $program->scope_from_surah === 1 && $program->scope_from_verse === 1
            && $program->scope_to_surah === 114 && isset($verses[114]) && $program->scope_to_verse === (int) $verses[114] ? 'khatma' : 'program';
    }

    public function label(string $kind, ?int $number, string $program, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($kind) {
            'surah' => __('quran::exams.stops.surah', ['name' => $this->surahName((int) $number, $locale)], $locale),
            'juz' => __('quran::exams.stops.juz', ['number' => $number], $locale),
            'program' => __('quran::exams.stops.program', ['name' => $program], $locale),
            'khatma' => __('quran::exams.stops.khatma', [], $locale),
            default => throw new InvalidArgumentException("No stop {$kind}"),
        };
    }

    /** The stop's name in both languages, for a message (each recipient reads theirs). */
    public function localized(string $kind, ?int $number, string $program): Localized
    {
        return new Localized($this->label($kind, $number, $program, 'ar'), $this->label($kind, $number, $program, 'en'));
    }

    /** The pages the stop covers in the program (one without the platform's copy, or on the Noorani Qaida). */
    public function pages(QuranProgram $program, string $kind, ?int $number): float
    {
        $layout = $this->mushaf->layout();
        if ($layout === null || $program->track === 'noorani') {
            return 1.0;
        }
        $order = LessonPortion::order($program->shape(), $layout);
        $indexes = match ($kind) {
            'surah' => array_values(array_filter($order, fn (int $i) => $layout->surahOf($i) === $number)),
            'juz' => array_values(array_filter($order, fn (int $i) => $layout->juzOf($i) === $number)),
            default => $order,
        };
        sort($indexes);

        return $indexes === [] ? 1.0 : max(Grading::MIN_PAGES, $layout->pagesIn($layout->ranges($indexes)));
    }

    private function surahName(int $number, string $locale): string
    {
        $this->surahs ??= array_column($this->quran->surahs(), null, 'number');
        $surah = $this->surahs[$number] ?? null;

        return $surah === null ? (string) $number : ($locale === 'ar' ? $surah['name_ar'] : $surah['name_en']);
    }
}
