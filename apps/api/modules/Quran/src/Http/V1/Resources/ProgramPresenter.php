<?php

namespace Modules\Quran\Http\V1\Resources;

use Modules\Quran\Contracts\DTOs\QuranPassage;
use Modules\Quran\Contracts\DTOs\VerseRange;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Programs\Portion;
use Modules\Quran\Domain\Programs\Position;
use Modules\Quran\Models\QuranLessonResult;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * A Quran plan, its portion and a lesson's results as the API answers them:
 * passages by surah and verses with a title in the reader's language (never
 * the Quran's words: the mushaf's pages carry them), grades with their
 * labels. Families never read a teacher's note, and the kids' look no counts.
 */
final class ProgramPresenter
{
    /** @var ?array<int, array{number: int, name_ar: string, name_en: string, verses: int}> */
    private ?array $surahs = null;

    public function __construct(
        private readonly QuranApi $quran,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(QuranPlan $plan, ?Portion $portion, ?string $frozen): array
    {
        $program = $plan->program;
        $shape = $plan->shape();

        return [
            'id' => $plan->id,
            'enrollment_id' => $plan->enrollment_id,
            'group_id' => $plan->group_id,
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                /** hifz, tilawa, talqin, noorani, review or ijaza */
                'track' => $program->track,
                'track_label' => __('quran::tracks.names.'.$program->track),
                /** forward (from al-Fatiha) or backward (from an-Nas) */
                'direction' => $program->direction,
                /** verses, lines, page, quarter_hizb or lesson (the Noorani Qaida) */
                'unit' => $program->unit,
                'unit_label' => __('quran::programs.units.'.$program->unit),
                'pass_grade' => $program->pass_grade,
                /** none, juz or surah */
                'exam_stops' => $program->exam_stops,
            ],
            /** The learner's own pace (the program's where the plan sets none). */
            'pace' => ['new_amount' => $shape->newAmount, 'near_portions' => $shape->nearPortions, 'far_pages' => $shape->farPages],
            /** Where the next new portion starts; null once the program is complete. */
            'position' => $this->position($plan->position()),
            'finished' => $plan->finished_at !== null,
            /** paused or ended: the plan waits where it is; null when it moves on */
            'frozen' => $frozen,
            'frozen_label' => $frozen === null ? null : __('quran::programs.frozen.'.$frozen),
            /** How much of the program's scope is behind the learner, 0 to 1. */
            'progress' => $portion?->progress ?? ($plan->finished_at !== null ? 1.0 : 0.0),
            'portion' => $portion === null ? null : $this->portion($portion),
        ];
    }

    /** @return array<string, mixed> */
    public function portion(Portion $portion): array
    {
        $part = fn (string $key) => $portion->part($key) !== [] ? [
            'label' => __('quran::programs.parts.'.$key),
            'ranges' => array_map(fn (VerseRange $r) => $this->range($r), $portion->part($key)),
            'pages' => $portion->pagesOf($key),
        ] : null;

        return [
            'new' => $part('new'),
            'near' => $part('near'),
            'far' => $part('far'),
            /** On the Noorani Qaida: the lesson's number and title. */
            'lesson' => $portion->lesson === null ? null : ['number' => $portion->lesson, 'title' => $this->nooraniTitle($portion->lesson)],
            /** The exam stops the new part completes (end of a surah or a part). */
            'stops' => array_map(fn (array $s) => $s + ['label' => $s['kind'] === 'surah'
                ? __('quran::programs.stops.surah', ['name' => $this->surahName($s['number'])])
                : __('quran::programs.stops.juz', ['number' => $s['number']])], $portion->stops),
            'finished' => $portion->finished,
        ];
    }

    /** @return array{surah: int, from: int, to: int, title: string} */
    public function range(VerseRange $range): array
    {
        return ['surah' => $range->surah, 'from' => $range->from, 'to' => $range->to, 'title' => $this->title($range->surah, $range->from, $range->to)];
    }

    /**
     * @param  bool  $family  a family reads no notes; $counts false: the kids' look, no counts either
     * @return array<string, mixed>
     */
    public function result(QuranLessonResult $result, bool $family = false, bool $counts = true): array
    {
        $settings = QuranSettings::current();
        $parts = [];
        foreach ($result->parts as $key => $part) {
            $parts[$key] = [
                'label' => __('quran::programs.parts.'.$key),
                'ranges' => array_map(fn (array $r) => $this->range(VerseRange::of($r['surah'], $r['from'], $r['to'])), $part['ranges'] ?? []),
                'lesson' => isset($part['lesson']) ? ['number' => $part['lesson'], 'title' => $this->nooraniTitle((int) $part['lesson'])] : null,
                /** excellent, very_good, good, acceptable or repeat */
                'grade' => $part['grade'],
                'grade_label' => __('quran::programs.grades.'.$part['grade']),
                'mistakes' => $counts ? collect($part['mistakes'] ?? [])->map(fn ($n, $kind) => ['kind' => $kind, 'label' => $settings->mistakeName((string) $kind), 'count' => (int) $n])->values()->all() : [],
            ] + ($family ? [] : [
                /** The grade the mistakes gave; grade differs when the teacher chose another (note says why). */
                'computed' => $part['computed'],
                'note' => $part['note'] ?? null,
            ]);
        }

        return [
            'id' => $result->id,
            'session_id' => $result->session_id,
            'student_id' => $result->student_id,
            'parts' => $parts,
            /** Whether the position moved past the new part. */
            'advanced' => $result->advanced,
            /** The course was paused or over: nothing moved. */
            'frozen' => $result->frozen,
            'recorded_at' => $this->time->present($result->recorded_at),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function grades(): array
    {
        return array_map(fn (string $g) => ['key' => $g, 'label' => __('quran::programs.grades.'.$g)], Grades::ORDER);
    }

    /** @return list<array{key: string, label: string, weight: float}> */
    public function mistakeTypes(): array
    {
        $settings = QuranSettings::current();

        return array_map(fn (array $t) => ['key' => $t['key'], 'label' => $settings->mistakeName($t['key']), 'weight' => (float) $t['weight']], $settings->mistakeTypes());
    }

    /** @return ?array{surah: ?int, verse: int, title: string} */
    private function position(?Position $at): ?array
    {
        if ($at === null) {
            return null;
        }

        return $at->toArray() + ['title' => $at->isLesson() ? $this->nooraniTitle($at->verse) : $this->title((int) $at->surah, $at->verse, $at->verse)];
    }

    private function title(int $surah, int $from, int $to): string
    {
        $ar = app()->getLocale() === 'ar';
        $n = fn (int $x) => $ar ? QuranPassage::arabicNumber($x) : (string) $x;

        return $this->surahName($surah).' '.($from === $to ? $n($from) : $n($from).'–'.$n($to));
    }

    private function surahName(int $number): string
    {
        $this->surahs ??= array_column($this->quran->surahs(), null, 'number');
        $surah = $this->surahs[$number] ?? null;

        return $surah === null ? (string) $number : (app()->getLocale() === 'ar' ? $surah['name_ar'] : $surah['name_en']);
    }

    private function nooraniTitle(int $lesson): string
    {
        $lessons = (array) trans('quran::tracks.noorani.lessons');

        return __('quran::programs.noorani_lesson', ['number' => $lesson, 'title' => $lessons[$lesson - 1] ?? '']);
    }
}
