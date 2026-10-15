<?php

namespace Modules\Learning\Queries\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\LearningSettings;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A learner's hifz map (docs/architecture/02, section 22): every page of
 * the mushaf by part, each new, learning, or memorized and strong, fading or
 * weak now; how many of each; and the daily review: the pages due by the end
 * of the learner's day, most overdue first, as many as the academy asks a day
 * less those already reviewed today.
 */
final class HifzMap
{
    public function __construct(
        private readonly HifzPages $pages,
        private readonly CurriculumApi $curriculum,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return array{pages_total: int, counts: array{memorized: int, learning: int, strong: int, fading: int, weak: int, due: int},
     *     juzs: list<array{juz: int, pages: list<array{page: int, strength: string}>}>}
     */
    public function for(string $studentId, CarbonImmutable $now): array
    {
        $cards = HifzPage::query()->where('student_id', $studentId)->get()->keyBy('page');
        $endOfDay = $this->endOfDay($studentId, $now);
        $counts = ['memorized' => 0, 'learning' => 0, 'strong' => 0, 'fading' => 0, 'weak' => 0, 'due' => 0];
        $juzs = [];
        foreach ($this->pages->layout() as ['page' => $page, 'juz' => $juz]) {
            $card = $cards->get($page);
            $strength = Scheduler::strength($card?->card(), $now);
            if ($card !== null) {
                $counts[$card->state]++;
                $counts['due'] += $card->due_at->lessThanOrEqualTo($endOfDay) ? 1 : 0;
                if ($card->state === 'memorized') {
                    $counts[$strength]++;
                }
            }
            $juzs[$juz]['juz'] = $juz;
            $juzs[$juz]['pages'][] = ['page' => $page, 'strength' => $strength];
        }

        return ['pages_total' => $this->pages->count(), 'counts' => $counts, 'juzs' => array_values($juzs)];
    }

    /**
     * Today's review on the learner's clock.
     *
     * @return array{daily: int, done_today: int, pages: list<array{page: int, juz: int, title: string, surahs: list<array{number: int, name_ar: string, name_en: string}>, strength: string, overdue_days: int}>}
     */
    public function wird(string $studentId, CarbonImmutable $now): array
    {
        $endOfDay = $this->endOfDay($studentId, $now);
        $startOfDay = $endOfDay->startOfDay();
        $daily = LearningSettings::current()->hifz_daily_pages;
        $done = DB::table('hifz_reviews')->where('student_id', $studentId)->whereIn('source', ['wird', 'self'])
            ->where('reviewed_at', '>=', $startOfDay)->distinct()->count('page');
        $due = HifzPage::query()->where('student_id', $studentId)->where('due_at', '<=', $endOfDay)
            ->orderBy('due_at')->limit(max(0, $daily - $done))->get();

        return ['daily' => $daily, 'done_today' => $done, 'pages' => $due->map(function (HifzPage $card) use ($now) {
            $page = $this->curriculum->quranPage($card->page);

            return [
                'page' => $card->page,
                'juz' => $page?->juz ?? $this->pages->juzOf($card->page),
                'title' => $page?->title() ?? '',
                'surahs' => $page?->surahs ?? [],
                'strength' => Scheduler::strength($card->card(), $now),
                'overdue_days' => max(0, (int) floor($card->due_at->diffInDays($now, false))),
            ];
        })->values()->all()];
    }

    /** The end of the learner's day, on their own clock. */
    private function endOfDay(string $studentId, CarbonImmutable $now): CarbonImmutable
    {
        $zone = DB::table('users')->where('id', $studentId)->value('timezone') ?: $this->tenants->require()->defaultTimezone;

        return $now->setTimezone($zone)->endOfDay();
    }
}
