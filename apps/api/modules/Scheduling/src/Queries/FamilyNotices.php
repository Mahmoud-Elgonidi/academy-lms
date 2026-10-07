<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\SharedKernel\Time\Timezones;

/** What a family is told above their lessons: coming holidays, and clock changes that move their hour. */
final class FamilyNotices
{
    /** @return list<array{name: string, period: string}> the academy's holidays in the next six weeks */
    public function holidays(): array
    {
        $now = CarbonImmutable::now();

        return AcademyHoliday::query()->where('ends_at', '>', $now)->where('starts_at', '<', $now->addWeeks(6))->orderBy('starts_at')->get()
            ->map(fn (AcademyHoliday $h) => ['name' => $h->name, 'period' => TimeOffView::holiday($h)])->all();
    }

    /**
     * Clock changes that move the learner's hour within two weeks (the DST
     * planner, as the family is told), on the viewer's clock; not where the
     * academy kept their hour.
     *
     * @return list<string>
     */
    public function clockChanges(string $learnerId, string $viewerTz): array
    {
        $now = CarbonImmutable::now();
        $locale = app()->getLocale();
        $at = fn (int $weekday, string $time) => CarbonImmutable::parse('2026-01-05 '.substr($time, 0, 5))->addDays($weekday - 1)->locale($locale)->translatedFormat('l H:i');

        return ShiftNotice::query()->where('student_id', $learnerId)->where('decision', '!=', 'keep_student')
            ->where('changes_at', '>', $now)->where('changes_at', '<=', $now->addDays(SendDstNotices::EARLY_DAYS))
            ->orderBy('changes_at')->get()
            ->map(fn (ShiftNotice $n) => __('scheduling::dst.family.notice', [
                'date' => $n->changes_at->setTimezone($viewerTz)->locale($locale)->translatedFormat('l j F'),
                'before' => $at($n->before_weekday, $n->before_time),
                'after' => $at($n->after_weekday, $n->after_time),
                'zone' => Timezones::city($n->other_tz),
                'cause' => Timezones::city($n->cause_tz),
            ]))->all();
    }
}
