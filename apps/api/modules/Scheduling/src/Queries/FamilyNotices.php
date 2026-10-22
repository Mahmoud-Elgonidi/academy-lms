<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Filament\TimeOffView;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\Timezones;

/**
 * What a family is told above their lessons: coming holidays, clock changes
 * that move their hour, and a course of the learner paused, ending or just
 * ended (never the office's reason).
 */
final class FamilyNotices
{
    /** How long after its end a course is still said to have ended. */
    public const ENDED_DAYS = 14;

    /**
     * The learner's courses paused (or to be, within six weeks), ending, or
     * ended in the last two weeks, in words, with days on the academy's calendar.
     *
     * @return list<string>
     */
    public function courses(string $learnerId): array
    {
        $days = app(CourseDays::class);
        $today = $days->today();
        $locale = app()->getLocale();
        $day = fn (CarbonImmutable $d) => $d->locale($locale)->translatedFormat('l j F');
        $courses = app(EnrollmentApi::class)->forStudent($learnerId);
        $subjects = DB::table('subjects')->whereIn('id', array_map(fn (EnrollmentData $e) => $e->subjectId, $courses))
            ->pluck($locale === 'ar' ? 'name_ar' : 'name_en', 'id');
        $l = 'scheduling::courses.family.';

        $out = [];
        foreach ($courses as $e) {
            $subject = (string) ($subjects[$e->subjectId] ?? '');
            $line = match (true) {
                $e->status === EnrollmentStatus::Ended => $e->endsOn !== null && $e->endsOn->greaterThanOrEqualTo($today->subDays(self::ENDED_DAYS))
                    ? __($l.'ended', ['subject' => $subject]) : null,
                $e->endsOn !== null => __($l.'ending', ['subject' => $subject, 'on' => $day($e->endsOn)]),
                $e->status === EnrollmentStatus::Paused && $e->pausedUntil !== null => __($l.'paused', ['subject' => $subject, 'until' => $day($e->pausedUntil)]),
                $e->pausedFrom !== null && $e->pausedUntil !== null && $e->pausedFrom->lessThanOrEqualTo($today->addWeeks(6)) => __($l.'pause_ahead', ['subject' => $subject, 'from' => $day($e->pausedFrom), 'until' => $day($e->pausedUntil)]),
                default => null,
            };
            if ($line !== null) {
                $out[] = (string) $line;
            }
        }

        return $out;
    }

    /** @return list<array{name: string, period: string}> the academy's holidays in the next six weeks */
    public function holidays(): array
    {
        $now = CarbonImmutable::now();

        return AcademyHoliday::query()->where('ends_at', '>', $now)->where('starts_at', '<', $now->addWeeks(6))->orderBy('starts_at')->get()
            ->map(fn (AcademyHoliday $h) => ['name' => $h->name, 'period' => TimeOffView::holiday($h)])->all();
    }

    /**
     * What is said about the learner above their lessons: their courses paused,
     * ending or just ended, then clock changes that move their hour.
     *
     * @return list<string>
     */
    public function aboutLearner(string $learnerId, string $viewerTz): array
    {
        return collect($this->courses($learnerId))->merge($this->clockChanges($learnerId, $viewerTz))->values()->all();
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
        $at = fn (int $weekday, string $time) => CarbonImmutable::parse('2026-01-05 '.substr($time, 0, 5))->addDays($weekday - 1)->locale($locale)->translatedFormat(Clock::pattern('l H:i'));

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
