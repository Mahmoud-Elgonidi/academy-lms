<?php

namespace Modules\Learning\Queries\Mushaf;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Learning\Contracts\LearningApi;
use Modules\Learning\Models\LessonMushaf as OpenPage;
use Modules\Learning\Models\RecitationMistake;
use Modules\Learning\Mushaf\MushafLesson;
use Modules\Learning\Mushaf\MushafLessons;
use Modules\Learning\Queries\Hifz\HifzMap;

/**
 * A page of a lesson's shared mushaf as every screen draws it: the page asked
 * for, else the one the teacher has open, else where the lesson starts (its
 * plan's first passage of the Quran, else the student's oldest page due for
 * review, else the first). Its verses come word by word from the platform's
 * copy, each word its surah, its verse and its place in the verse from 1;
 * with the words marked in this lesson, and the ones marked on this page in
 * the student's earlier lessons.
 */
final class LessonMushaf
{
    public function __construct(
        private readonly CurriculumApi $curriculum,
        private readonly MushafLessons $lessons,
        private readonly HifzMap $map,
    ) {}

    /** @return ?array<string, mixed> null when the page is not in the mushaf */
    public function page(MushafLesson $lesson, ?int $page, CarbonImmutable $now): ?array
    {
        $teacherPage = OpenPage::query()->where('session_id', $lesson->card->id)->value('page');
        $start = $this->startPage($lesson, $now);
        $found = $this->curriculum->quranPage($page ?? $teacherPage ?? $start);
        if ($found === null) {
            return null;
        }
        $verses = array_map(fn (array $v) => [
            'surah' => $v['surah'],
            'verse' => $v['verse'],
            'words' => array_slice($found->words, $v['first'] - 1, $v['last'] - $v['first'] + 1),
        ], $found->verses);
        $starts = array_filter($verses, fn (array $v) => $v['verse'] === 1 && ! in_array($v['surah'], [1, 9], true));

        return [
            'session_id' => $lesson->card->id,
            'role' => $lesson->role,
            'can_mark' => $this->lessons->marksOpen($lesson, $now),
            'page' => $found->page,
            'teacher_page' => $teacherPage === null ? null : (int) $teacherPage,
            'start_page' => $start,
            'pages_total' => count($this->curriculum->quranPages()),
            'juz' => $found->juz,
            'title' => $found->title(),
            'surahs' => $found->surahs,
            'basmala' => $starts === [] ? null : $this->curriculum->passage(TextSource::quran(1, 1, 1))?->words,
            'verses' => $verses,
            'marks' => $this->onPage($found->verses, fn (Builder $q) => $q->where('session_id', $lesson->card->id))
                ->map(fn ($m) => ['surah' => (int) $m->surah, 'verse' => (int) $m->verse, 'word' => (int) $m->word, 'kind' => $m->kind])->all(),
            'earlier' => $this->onPage($found->verses, fn (Builder $q) => $q->where('student_id', $lesson->studentId())->where('session_id', '!=', $lesson->card->id), true)
                ->map(fn ($m) => ['surah' => (int) $m->surah, 'verse' => (int) $m->verse, 'word' => (int) $m->word, 'kind' => $m->kind, 'times' => (int) $m->times])->all(),
            'attribution' => $found->attribution,
        ];
    }

    /** Where the lesson starts: its plan's first passage of the Quran, else the student's oldest page due, else the first. */
    public function startPage(MushafLesson $lesson, CarbonImmutable $now): int
    {
        $plan = app(LearningApi::class)->planFor($lesson->card->enrollmentId, $lesson->card->startsAt, true);
        foreach ($this->curriculum->textSources(array_map(fn ($line) => $line->lessonId, $plan)) as $source) {
            if ($source->isQuran() && ($pages = $this->curriculum->quranPagesOf($source)) !== []) {
                return $pages[0];
            }
        }

        return $this->map->wird($lesson->studentId(), $now)['pages'][0]['page'] ?? 1;
    }

    /**
     * The marks on a page's verses, narrowed by $where; grouped by word (how many times, the latest kind) when $grouped.
     *
     * @param  list<array{surah: int, verse: int}>  $verses
     */
    private function onPage(array $verses, \Closure $where, bool $grouped = false): Collection
    {
        $ranges = [];
        foreach ($verses as $v) {
            $ranges[$v['surah']] = [min($ranges[$v['surah']][0] ?? $v['verse'], $v['verse']), max($ranges[$v['surah']][1] ?? $v['verse'], $v['verse'])];
        }
        $query = DB::table((new RecitationMistake)->getTable())->where($where)->where(function (Builder $q) use ($ranges) {
            foreach ($ranges as $surah => [$from, $to]) {
                $q->orWhere(fn (Builder $r) => $r->where('surah', $surah)->whereBetween('verse', [$from, $to]));
            }
        });
        if (! $grouped) {
            return $query->orderBy('surah')->orderBy('verse')->orderBy('word')->get(['surah', 'verse', 'word', 'kind']);
        }

        return $query->groupBy('surah', 'verse', 'word')->orderBy('surah')->orderBy('verse')->orderBy('word')
            ->selectRaw('surah, verse, word, count(*) as times, (array_agg(kind order by updated_at desc))[1] as kind')->get();
    }
}
