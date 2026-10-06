<?php

namespace Modules\Learning\Filament\Family;

use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\ViewerTimezone;

/** Home, for guardians and adults: progress on each course, homework to do, and the teacher's last words. Students with a stage get JourneyWidget. */
final class LearningHomeWidget extends Widget
{
    protected string $view = 'learning::filament.family.home';

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return FamilyLearning::stage() === LearnerStage::Classic;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        $learner = FamilyLearning::learner();
        if ($learner === null) {
            return ['courses' => [], 'homework' => 0, 'next_due' => null, 'report' => null];
        }
        $tz = app(ViewerTimezone::class)->effective();
        $open = Homework::query()->where('student_id', $learner->id)->where('status', 'assigned');
        $nextDue = (clone $open)->orderBy('due_at')->value('due_at');
        $report = SessionReport::query()->where('student_id', $learner->id)->whereIn('status', ['submitted', 'reviewed'])
            ->where('kind', 'lesson')->orderByDesc('session_starts_at')->first();

        return [
            'courses' => FamilyLearning::courses($learner->id),
            'homework' => $open->count(),
            'next_due' => $nextDue ? FamilyLearning::date(CarbonImmutable::parse($nextDue), $tz, 'l j F') : null,
            'report' => $report ? [
                'date' => FamilyLearning::date($report->session_starts_at, $tz, 'l j F'),
                'overall' => $report->overall,
                'note' => $report->notes_for_student,
            ] : null,
        ];
    }
}
