<?php

namespace Modules\Learning\Filament\Family;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\Clock;

/** What the family pages share: the learner on screen, the look of the area, their courses, and dates on the viewer's clock. */
final class FamilyLearning
{
    public static function learner(): ?Learner
    {
        return app(FamilyView::class)->current((string) auth()->id());
    }

    /** The viewer's look: a student's own stage, the classic one for a guardian. */
    public static function stage(): LearnerStage
    {
        return app(FamilyView::class)->stage((string) auth()->id());
    }

    /** @return list<array{id: string, subject: ?string, teacher: ?string, map: ?array<string, mixed>, state: ?string}> */
    public static function courses(string $learnerId): array
    {
        $enrollments = array_values(array_filter(app(EnrollmentApi::class)->forStudent($learnerId), fn (EnrollmentData $e) => $e->status->value !== 'ended'));
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');
        $teachers = DB::table('users')->whereIn('id', array_map(fn (EnrollmentData $e) => $e->teacherId, $enrollments))->pluck('name', 'id');

        return array_map(fn (EnrollmentData $e) => [
            'id' => $e->id,
            'subject' => $subjects[$e->subjectId] ?? null,
            'teacher' => $teachers[$e->teacherId] ?? null,
            'map' => app(RoadmapQuery::class)->for($e->id, RoadmapQuery::MATERIALS_FAMILY),
            'state' => self::state($e),
        ], $enrollments);
    }

    /** A course paused (or to be), or ending, in a few words with its days on the academy's calendar; null when it simply goes on. */
    public static function state(EnrollmentData $e): ?string
    {
        $l = 'learning::family.progress.';
        $day = fn (CarbonImmutable $d) => $d->locale(app()->getLocale())->translatedFormat('l j F');

        return match (true) {
            $e->endsOn !== null => __($l.'ending', ['on' => $day($e->endsOn)]),
            $e->status->value === 'paused' && $e->pausedUntil !== null => __($l.'paused', ['until' => $day($e->pausedUntil)]),
            $e->pausedFrom !== null && $e->pausedUntil !== null => __($l.'pause_ahead', ['from' => $day($e->pausedFrom), 'until' => $day($e->pausedUntil)]),
            default => null,
        };
    }

    public static function date(CarbonImmutable $at, string $tz, string $format = 'l j F'): string
    {
        return $at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern($format));
    }
}
