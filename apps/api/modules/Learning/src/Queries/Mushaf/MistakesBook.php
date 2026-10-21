<?php

namespace Modules\Learning\Queries\Mushaf;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;

/**
 * A learner's mistakes notebook (docs/architecture/02, section 22): the
 * words their teachers marked in the last months, by surah and verse, each
 * verse as the platform's copy writes it with its marked words (how many
 * times, what they were, when last). Read from the marks each time.
 */
final class MistakesBook
{
    /** The notebook covers the marks of the last half year. */
    public const MONTHS = 6;

    /** Words shown at most, the most recent first kept. */
    public const WORDS = 300;

    public function __construct(private readonly CurriculumApi $curriculum) {}

    /**
     * @return array{words: int, surahs: list<array{surah: int, name_ar: string, name_en: string, words: int,
     *     verses: list<array{verse: int, words: list<string>, marks: list<array{word: int, times: int, kinds: list<string>, last_at: CarbonImmutable}>}>}>}
     */
    public function for(string $studentId, CarbonImmutable $now): array
    {
        $rows = DB::table('recitation_mistakes')->where('student_id', $studentId)->where('updated_at', '>=', $now->subMonths(self::MONTHS))
            ->groupBy('surah', 'verse', 'word')
            ->selectRaw('surah, verse, word, count(*) as times, array_to_string(array_agg(distinct kind), \',\') as kinds, max(updated_at) as last_at')
            ->orderByDesc('last_at')->limit(self::WORDS)->get()
            ->sortBy([['surah', 'asc'], ['verse', 'asc'], ['word', 'asc']]);
        $names = collect($this->curriculum->surahs())->keyBy('number');
        $surahs = [];
        foreach ($rows->groupBy('surah') as $surah => $marks) {
            $verses = [];
            foreach ($marks->groupBy('verse') as $verse => $words) {
                $passage = $this->curriculum->passage(TextSource::quran((int) $surah, (int) $verse, (int) $verse));
                if ($passage === null) {
                    continue;
                }
                $verses[] = [
                    'verse' => (int) $verse,
                    'words' => $passage->words,
                    'marks' => $words->map(fn ($w) => [
                        'word' => (int) $w->word, 'times' => (int) $w->times,
                        'kinds' => explode(',', (string) $w->kinds), 'last_at' => CarbonImmutable::parse($w->last_at),
                    ])->values()->all(),
                ];
            }
            if ($verses !== []) {
                $surahs[] = [
                    'surah' => (int) $surah,
                    'name_ar' => (string) ($names[$surah]['name_ar'] ?? ''),
                    'name_en' => (string) ($names[$surah]['name_en'] ?? ''),
                    'words' => array_sum(array_map(fn (array $v) => count($v['marks']), $verses)),
                    'verses' => $verses,
                ];
            }
        }

        return ['words' => array_sum(array_column($surahs, 'words')), 'surahs' => $surahs];
    }
}
