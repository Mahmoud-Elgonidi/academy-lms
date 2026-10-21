<?php

namespace Modules\Learning\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\RecitationMistake;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;

/**
 * The demo's mistakes notebook: a few words marked in the last Quran lesson
 * each learner on a Quran course was given a report for, as their teacher
 * would have in the lesson's mushaf, on that lesson's own passage by the
 * platform's word numbers. Only once the server has the Quran's text, and
 * only for a learner with no marks yet.
 */
final class DemoMistakesSeeder extends Seeder
{
    /** Where the marks go in the passage's first two verses: verse (0 or 1), word from 1 (or from the end, negative), kind. */
    private const MARKS = [[0, 2, 'wrong'], [0, -1, 'hesitation'], [1, 1, 'tajweed'], [1, 3, 'wrong']];

    public function run(CurriculumApi $curriculum, HifzPages $pages): void
    {
        if ($pages->count() === 0) {
            return;
        }
        $done = [];
        $reports = SessionReport::query()->where('kind', 'lesson')->whereIn('status', ['submitted', 'reviewed'])->orderByDesc('session_starts_at')->get();
        foreach ($reports as $report) {
            $student = (string) $report->student_id;
            if (isset($done[$student])) {
                continue;
            }
            if (RecitationMistake::query()->where('student_id', $student)->exists()) {
                $done[$student] = true;

                continue;
            }
            $sources = $curriculum->textSources(ReportItem::query()->where('report_id', $report->id)->pluck('node_id')->all());
            $quran = collect($sources)->first(fn ($source) => $source->isQuran());
            $verses = $quran === null ? [] : ($curriculum->passage($quran)?->verses ?? []);
            if ($verses === []) {
                continue;
            }
            $at = CarbonImmutable::parse($report->session_starts_at)->addMinutes(15);
            foreach (self::MARKS as [$index, $word, $kind]) {
                $verse = $verses[$index] ?? null;
                if ($verse === null) {
                    continue;
                }
                $count = $verse['last'] - $verse['first'] + 1;
                $place = $word < 0 ? $count + $word + 1 : $word;
                if ($place < 1 || $place > $count) {
                    continue;
                }
                RecitationMistake::query()->firstOrNew(['session_id' => $report->session_id, 'surah' => $verse['surah'], 'verse' => $verse['verse'], 'word' => $place])
                    ->forceFill([
                        'student_id' => $student, 'enrollment_id' => $report->enrollment_id, 'teacher_id' => $report->teacher_id, 'kind' => $kind,
                        'created_at' => $at, 'updated_at' => $at,
                    ])->save();
            }
            $done[$student] = true;
        }
    }
}
