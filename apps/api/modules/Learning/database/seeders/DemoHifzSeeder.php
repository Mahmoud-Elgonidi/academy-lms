<?php

namespace Modules\Learning\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Learning\Contracts\Events\SessionReportSubmitted;
use Modules\Learning\Hifz\HifzFromLessons;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\HifzPage;
use Modules\Learning\Models\SessionReport;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The demo learners' hifz maps from the lesson reports already handed in,
 * oldest first, as they would have been made had the lessons read their
 * surahs then (a demo older than its lessons' texts). Only once the server
 * has the Quran's text, and only for a learner with no pages yet; each
 * review is kept once, so it can run again.
 */
final class DemoHifzSeeder extends Seeder
{
    public function run(HifzFromLessons $hifz, HifzPages $pages, TenantContext $tenants): void
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
    }
}
