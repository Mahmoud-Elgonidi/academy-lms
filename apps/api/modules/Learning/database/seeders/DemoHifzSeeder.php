<?php

namespace Modules\Learning\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Actions\Hifz\MarkMemorized;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Hifz\HifzFromLessons;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\Hifz\HifzMap;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The demo learners' hifz maps from the lesson reports already handed in,
 * oldest first, as they would have been made had the lessons read their
 * surahs then (a demo older than its lessons' texts); a learner on a Quran
 * course still with no pages is said to know the mushaf's last three pages,
 * as their teacher would say it (MarkMemorized: two well a week ago, one
 * shakily a few days ago, so the daily review has something for them). Only once the
 * server has the Quran's text, and only for a learner with no pages yet;
 * each review is kept once, so it can run again.
 */
final class DemoHifzSeeder extends Seeder
{
    /** How many of the mushaf's last pages a learner with no map is said to know. */
    private const KNOWN_PAGES = 3;

    public function run(HifzFromLessons $hifz, HifzPages $pages, TenantContext $tenants, HifzMap $map, MarkMemorized $mark): void
    {
        if ($pages->count() === 0) {
            return;
        }
        $academy = $tenants->require()->id;
        $mapped = HifzPage::query()->distinct()->pluck('student_id')->all();
        $reports = SessionReport::query()->where('kind', 'lesson')->whereIn('status', ['submitted', 'reviewed'])
            ->whereNotIn('student_id', $mapped)->orderBy('session_starts_at')->get();
        foreach ($reports as $report) {
            $hifz->reportSubmitted(new SessionReportSubmitted(
                $academy, (string) $report->id, (string) $report->session_id, $report->enrollment_id,
                (string) $report->student_id, (string) $report->teacher_id, 'lesson', false,
            ));
        }

        $last = range($pages->count() - self::KNOWN_PAGES + 1, $pages->count());
        $mapped = HifzPage::query()->distinct()->pluck('student_id')->flip();
        $readsQuran = [];
        $courses = DB::table('enrollments')->where('status', '!=', 'ended')->whereNotNull('curriculum_version_id')->get(['student_id', 'curriculum_version_id']);
        foreach ($courses as $course) {
            if ($mapped->has($course->student_id) || ! ($readsQuran[$course->curriculum_version_id] ??= $map->readsQuran((string) $course->curriculum_version_id))) {
                continue;
            }
            $mark->handle((string) $course->student_id, array_slice($last, 1), 'strong', CarbonImmutable::now()->subWeek());
            $mark->handle((string) $course->student_id, [$last[0]], 'weak', CarbonImmutable::now()->subDays(4));
            $mapped[$course->student_id] = true;
        }
    }
}
