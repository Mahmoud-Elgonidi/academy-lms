<?php

namespace Modules\Learning\Hifz;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\Hifz\RecordHifzReview;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\RecitationMistake;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Models\SubmissionCheck;
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
        private readonly CurriculumApi $curriculum,
        private readonly RecordHifzReview $review,
        private readonly HifzPages $pages,
    ) {}

    public function reportSubmitted(SessionReportSubmitted $event): void
    {
        $report = $event->kind === 'lesson' ? SessionReport::query()->find($event->reportId) : null;
        if ($report === null) {
            return;
        }
        $at = CarbonImmutable::parse($report->session_starts_at);
        $seen = [];
        foreach ($this->markedPages($report->session_id) as $page => $grade) {
            $this->review->handle($event->studentId, $page, $grade, 'mistakes', $report->session_id, $at);
            $seen[$page] = true;
        }
        $items = ReportItem::query()->where('report_id', $report->id)->get(['node_id', 'outcome']);
        $sources = $this->curriculum->textSources($items->pluck('node_id')->all());
        foreach ($items as $item) {
            $source = $sources[$item->node_id] ?? null;
            if (! $source?->isQuran()) {
                continue;
            }
            foreach ($this->curriculum->quranPagesOf($source) as $page) {
                if (isset($seen[$page])) {
                    continue;
                }
                $seen[$page] = true;
                $known = HifzPage::query()->where('student_id', $event->studentId)->where('page', $page)->exists();
                [$grade, $memorized] = match ($item->outcome) {
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
    private function markedPages(string $sessionId): array
    {
        $marks = RecitationMistake::query()->where('session_id', $sessionId)->get(['surah', 'verse', 'kind']);
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
