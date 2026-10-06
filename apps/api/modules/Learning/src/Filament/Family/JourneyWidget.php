<?php

namespace Modules\Learning\Filament\Family;

use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Modules\Learning\Models\Homework;
use Modules\Learning\Queries\JourneyQuery;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * A student's home: the roadmap and where they are on it, in their stage's
 * look (a map with stars for children, worlds and points for middle
 * school, a plan with a goal for teenagers). Guardians and adults keep the
 * classic cards (LearningHomeWidget).
 */
final class JourneyWidget extends Widget
{
    protected string $view = 'learning::filament.family.journey.widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /** Drawn with the page: no placeholder that jumps, no extra request. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return FamilyLearning::stage() !== LearnerStage::Classic;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return self::journey();
    }

    /**
     * What the stage views need, for the home and the full map page.
     *
     * @return array<string, mixed>
     */
    public static function journey(): array
    {
        $learner = FamilyLearning::learner();
        $open = Homework::query()->where('student_id', $learner?->id)->where('status', 'assigned');
        $nextDue = (clone $open)->orderBy('due_at')->value('due_at');

        return [
            'stage' => FamilyLearning::stage()->value,
            'learner' => $learner,
            'journey' => $learner ? app(JourneyQuery::class)->for($learner->id) : null,
            'homework' => $learner ? $open->count() : 0,
            'next_due' => $nextDue ? FamilyLearning::date(CarbonImmutable::parse($nextDue), app(ViewerTimezone::class)->effective(), 'l j F') : null,
        ];
    }
}
