<?php

namespace Modules\Quran\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Contracts\LearningApi;
use Modules\Quran\Actions\Hifz\RecordHifzReview;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Domain\Hifz\Scheduler;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\RecitationMistake;
use Modules\Quran\Models\SubmissionCheck;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Ai\RecitationResult;

/**
 * What the lessons tell the hifz map, each counted once: a report on a
 * lesson whose text is a passage of the Quran reviews its pages by the
 * lesson's outcome (mastered: memorized; practised or introduced: learning;
 * needs review: forgotten), and a recited homework's check reviews each
 * page by the share of its words said right. Words the teacher marked in
 * the lesson's shared mushaf say more than its outcome: when the report is
 * handed in, a page with three wrong words or more is forgotten, one with a
 * wrong word or two hesitations hard (tajweed alone says nothing of the
 * memorizing), and its outcome is not counted again for that page.
 */
final class HifzFromLessons
{
    public function __construct(
        private readonly QuranApi $quran,
        private readonly RecordHifzReview $review,
        private readonly HifzPages $pages,
        private readonly QuranArea $area,
        private readonly LearningApi $learning,
    ) {}

    public function reportSubmitted(SessionReportSubmitted $event): void
    {
        if (! $this->area->on()) {
            return;
        }
        $report = $event->kind === 'lesson' ? $this->learning->lessonReport($event->reportId) : null;
        if ($report === null) {
            return;
        }
        $at = $report->sessionStartsAt;
        // Pages the lesson's Quran result (RecordQuranLesson) already reviewed for the student are not counted again.
        $results = DB::table('quran_lesson_results')->where('session_id', $report->sessionId)->where('student_id', $event->studentId)->pluck('id')->all();
        $seen = $results === [] ? [] : DB::table('hifz_reviews')->where('student_id', $event->studentId)->where('source', 'lesson')->whereIn('source_id', $results)
            ->pluck('page')->mapWithKeys(fn ($p) => [(int) $p => true])->all();
        // A group lesson's marks are each student's own; its reviews are kept by each student's report.
        $group = DB::table('sessions')->where('id', $report->sessionId)->whereNotNull('group_id')->exists();
        foreach ($this->markedPages($report->sessionId, $event->studentId) as $page => $grade) {
            if (isset($seen[$page])) {
                continue;
            }
            $this->review->handle($event->studentId, $page, $grade, 'mistakes', $group ? $report->id : $report->sessionId, $at);
            $seen[$page] = true;
        }
        $sources = $this->quran->textSources(array_map('strval', array_keys($report->outcomes)));
        foreach ($report->outcomes as $nodeId => $outcome) {
            $source = $sources[$nodeId] ?? null;
            if ($source === null) {
                continue;
            }
            foreach ($this->quran->quranPagesOf($source) as $page) {
                if (isset($seen[$page])) {
                    continue;
                }
                $seen[$page] = true;
                $known = HifzPage::query()->where('student_id', $event->studentId)->where('page', $page)->exists();
                [$grade, $memorized] = match ($outcome) {
                    'mastered' => [Scheduler::GOOD, true],
                    'practiced' => [$known ? Scheduler::GOOD : Scheduler::HARD, false],
                    'introduced' => [$known ? null : Scheduler::HARD, false],
                    default => [Scheduler::AGAIN, false],
                };
                if ($grade !== null) {
                    $this->review->handle($event->studentId, $page, $grade, 'report', $report->id, $at, $memorized);
                }
            }
        }
    }

    /**
     * The pages the lesson's marked words say something of, with the grade they give.
     *
     * @return array<int, int> page => grade
     */
    private function markedPages(string $sessionId, string $studentId): array
    {
        $marks = RecitationMistake::query()->where('session_id', $sessionId)->where('student_id', $studentId)->get(['surah', 'verse', 'kind']);
        $pages = $this->pages->ofVerses($marks->map(fn (RecitationMistake $m) => ['surah' => $m->surah, 'verse' => $m->verse])->all());
        $count = [];
        foreach ($marks->values() as $i => $mark) {
            if (($pages[$i] ?? 0) !== 0) {
                $count[$pages[$i]][$mark->kind] = ($count[$pages[$i]][$mark->kind] ?? 0) + 1;
            }
        }
        $grades = [];
        foreach ($count as $page => $kinds) {
            [$wrong, $hesitation] = [$kinds['wrong'] ?? 0, $kinds['hesitation'] ?? 0];
            $grade = match (true) {
                $wrong >= 3 => Scheduler::AGAIN,
                $wrong >= 1 || $hesitation >= 2 => Scheduler::HARD,
                default => null,
            };
            if ($grade !== null) {
                $grades[$page] = $grade;
            }
        }

        return $grades;
    }

    /** A recited homework's check: each page it covers, by the share of that page's words said right. */
    public function recited(SubmissionCheck $check, RecitationResult $result, string $studentId, CarbonImmutable $at): void
    {
        $onPage = $this->pages->ofVerses(array_map(fn (array $v) => ['surah' => $v['surah'] ?? 0, 'verse' => $v['verse'] ?? 0], $result->verses));
        $words = [];
        foreach ($result->verses as $i => $verse) {
            $page = $onPage[$i] ?? 0;
            if ($page === 0) {
                continue;
            }
            foreach ($verse['words'] as $word) {
                $words[$page][] = $word['state'] === 'ok';
            }
        }
        foreach ($words as $page => $said) {
            $this->review->handle($studentId, $page, Scheduler::gradeFor(count(array_filter($said)) / count($said)), 'check', $check->id, $at);
        }
    }
}
