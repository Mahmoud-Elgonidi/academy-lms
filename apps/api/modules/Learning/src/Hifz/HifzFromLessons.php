<?php

namespace Modules\Learning\Hifz;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Actions\Hifz\RecordHifzReview;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Models\SubmissionCheck;
use Modules\SharedKernel\Ai\RecitationResult;

/**
 * What the lessons tell the hifz map, each counted once: a report on a
 * lesson whose text is a passage of the Quran reviews its pages by the
 * lesson's outcome (mastered: memorized; practised or introduced: learning;
 * needs review: forgotten), and a recited homework's check reviews each
 * page by the share of its words said right.
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
        $items = ReportItem::query()->where('report_id', $report->id)->get(['node_id', 'outcome']);
        $sources = $this->curriculum->textSources($items->pluck('node_id')->all());
        $seen = [];
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
                    $this->review->handle($event->studentId, $page, $grade, 'report', $report->id, CarbonImmutable::parse($report->session_starts_at), $memorized);
                }
            }
        }
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
