<?php

namespace Modules\Learning\Filament\Family;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;

/** What the family pages share: the learner on screen, their courses, and dates on the viewer's clock. */
final class FamilyLearning
{
    public static function learner(): ?Learner
    {
        return app(FamilyView::class)->current((string) auth()->id());
    }

    /** @return list<array{id: string, subject: ?string, teacher: ?string, map: ?array<string, mixed>}> */
    public static function courses(string $learnerId): array
    {
        $enrollments = array_values(array_filter(app(EnrollmentApi::class)->forStudent($learnerId), fn (EnrollmentData $e) => $e->status->value !== 'ended'));
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');
        $teachers = DB::table('users')->whereIn('id', array_map(fn (EnrollmentData $e) => $e->teacherId, $enrollments))->pluck('name', 'id');

        return array_map(fn (EnrollmentData $e) => [
            'id' => $e->id,
            'subject' => $subjects[$e->subjectId] ?? null,
            'teacher' => $teachers[$e->teacherId] ?? null,
            'map' => app(RoadmapQuery::class)->for($e->id),
        ], $enrollments);
    }

    public static function date(CarbonImmutable $at, string $tz, string $format = 'l j F'): string
    {
        return $at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat($format);
    }
}
