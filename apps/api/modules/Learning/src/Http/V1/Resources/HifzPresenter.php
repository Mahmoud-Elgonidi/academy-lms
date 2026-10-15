<?php

namespace Modules\Learning\Http\V1\Resources;

use Carbon\CarbonImmutable;
use Modules\Curriculum\Contracts\DTOs\QuranPage;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\HifzRecitation;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** The hifz map, its pages and a recited page's check, as the apps and the portal read them. */
final class HifzPresenter
{
    public function __construct(
        private readonly HifzMap $map,
        private readonly LocalTimePresenter $time,
    ) {}

    /** @return array<string, mixed> */
    public function map(string $studentId, CarbonImmutable $now): array
    {
        $map = $this->map->for($studentId, $now);

        return [
            /** 604 once the platform has the Quran's text; 0 until then (nothing to show) */
            'pages_total' => $map['pages_total'],
            /** memorized and learning pages; of the memorized: strong, fading and weak now; due by the end of the learner's day */
            'counts' => $map['counts'],
            /** Each part's pages: strength new, learning, strong, fading or weak. */
            'juzs' => $map['juzs'],
            'strengths' => trans('learning::hifz.strengths'),
        ];
    }

    /** @return array<string, mixed> */
    public function wird(string $studentId, CarbonImmutable $now): array
    {
        $wird = $this->map->wird($studentId, $now);

        return [
            /** pages a day at most (the academy's) */
            'daily' => $wird['daily'],
            'done_today' => $wird['done_today'],
            /** Today's pages, most overdue first: what is left of today's review. */
            'pages' => $wird['pages'],
        ];
    }

    /** @return array<string, mixed> */
    public function page(QuranPage $page, ?HifzPage $card, CarbonImmutable $now): array
    {
        return [
            'page' => $page->page,
            'juz' => $page->juz,
            'title' => $page->title(),
            'surahs' => $page->surahs,
            /** The page's verses as Tanzil writes them. */
            'verses' => array_map(fn (array $v) => ['surah' => $v['surah'], 'verse' => $v['verse'], 'text' => $v['text']], $page->verses),
            /** The attribution the text is shown with. */
            'attribution' => $page->attribution,
            'strength' => Scheduler::strength($card?->card(), $now),
            'due_at' => $card === null ? null : $this->time->present($card->due_at),
        ];
    }

    /** @return array<string, mixed> */
    public function recitation(HifzRecitation $r): array
    {
        $result = $r->result ?? [];

        return [
            'id' => $r->id,
            'page' => $r->page,
            /** pending, ready, failed */
            'status' => $r->status,
            'status_label' => __('learning::recitation.statuses.'.$r->status),
            /** Why it could not be heard, in the reader's words. */
            'failure' => $r->status === 'failed' ? (new AiFailed((string) $r->reason))->sayable() : null,
            'summary_label' => isset($result['summary']) ? __('learning::recitation.summary', $result['summary']) : null,
            /** 1 forgot, 2 hard, 3 good, 4 easy: the review it counted as */
            'grade' => $r->grade,
            'grade_label' => $r->grade === null ? null : __('learning::hifz.grades.'.$r->grade),
            /** Each verse's words: state ok, missing or wrong, and what was said instead. */
            'verses' => $result['verses'] ?? [],
            /** Words said that are not in the text, after the word they followed (counted from 1 through the page). */
            'extra' => $result['extra'] ?? [],
            'recited_at' => $this->time->present($r->created_at),
            'note' => __('learning::hifz.check_note'),
        ];
    }
}
