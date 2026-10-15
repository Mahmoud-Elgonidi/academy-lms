<?php

namespace Modules\Learning\Hifz;

use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;

/**
 * The mushaf's pages as the hifz map counts them (from the platform's copy,
 * CurriculumApi): which there are, which a part or a surah takes, and which
 * page each verse of a passage is on.
 */
final class HifzPages
{
    /** @var ?list<array{page: int, juz: int}> */
    private ?array $layout = null;

    public function __construct(private readonly CurriculumApi $curriculum) {}

    /** @return list<array{page: int, juz: int}> */
    public function layout(): array
    {
        return $this->layout ??= $this->curriculum->quranPages();
    }

    public function count(): int
    {
        return count($this->layout());
    }

    public function exists(int $page): bool
    {
        return $page >= 1 && $page <= $this->count();
    }

    public function juzOf(int $page): int
    {
        return $this->layout()[$page - 1]['juz'] ?? 0;
    }

    /**
     * The pages chosen by parts, surahs or pages themselves, in order, each once.
     *
     * @param  list<int>  $juzs
     * @param  list<int>  $surahs
     * @param  list<int>  $pages
     * @return list<int>
     *
     * @throws ValidationException naming what is not there
     */
    public function chosen(array $juzs = [], array $surahs = [], array $pages = []): array
    {
        $all = [];
        foreach ($juzs as $juz) {
            $found = array_column(array_filter($this->layout(), fn (array $p) => $p['juz'] === (int) $juz), 'page');
            $found !== [] ? array_push($all, ...$found) : throw ValidationException::withMessages(['juz' => __('learning::hifz.errors.juz')]);
        }
        foreach ($surahs as $surah) {
            $verses = collect($this->curriculum->surahs())->firstWhere('number', (int) $surah)['verses'] ?? 0;
            $found = $verses > 0 ? $this->curriculum->quranPagesOf(TextSource::quran((int) $surah, 1, $verses)) : [];
            $found !== [] ? array_push($all, ...$found) : throw ValidationException::withMessages(['surahs' => __('learning::hifz.errors.surah')]);
        }
        foreach ($pages as $page) {
            $this->exists((int) $page) ? $all[] = (int) $page : throw ValidationException::withMessages(['pages' => __('learning::hifz.errors.page')]);
        }
        $all = array_values(array_unique($all));
        sort($all);

        return $all;
    }

    /**
     * The page each verse of a passage of the Quran is on.
     *
     * @param  list<array{surah: int, verse: int}>  $verses
     * @return list<int> in the verses' order
     */
    public function ofVerses(array $verses): array
    {
        return array_map(fn (array $v) => $this->curriculum->quranPagesOf(TextSource::quran((int) $v['surah'], (int) $v['verse'], (int) $v['verse']))[0] ?? 0, $verses);
    }
}
