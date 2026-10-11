<?php

namespace Modules\Learning\Filament\Family;

use Filament\Widgets\Widget;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;

/**
 * The journey on the family home (docs/architecture/02, section 18): a
 * student's home is their journey itself (JourneyBoard, in their stage's
 * look); a guardian's or an adult's home starts with its card ("رحلة زياد":
 * where they are, the missions waiting, the stars), which opens the journey
 * page (FamilyJourney).
 */
final class JourneyWidget extends Widget
{
    protected string $view = 'learning::filament.family.map.home';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    /** Drawn with the page: no placeholder that jumps, no extra request. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return FamilyLearning::learner() !== null;
    }

    /** The whole journey on a student's home; a card on a guardian's or an adult's. */
    public function board(): bool
    {
        return FamilyLearning::stage() !== LearnerStage::Classic;
    }

    /** @return array{name: string, lesson: ?string, percent: ?int, stars: ?int, rank: string, missions: int} */
    public function card(): array
    {
        $learner = FamilyLearning::learner();
        $journey = app(JourneyMap::class)->for($learner->id, app(FamilyView::class)->stage($learner->id)->value, ! $learner->isSelf);
        $here = collect($journey['courses'])->first(fn (array $c) => $c['has_curriculum'] && ! $c['finished']) ?? $journey['courses'][0] ?? null;

        return [
            'name' => explode(' ', trim($learner->name))[0] ?: $learner->name,
            'lesson' => $here['current']['title'] ?? null,
            'percent' => $here['percent'] ?? null,
            'stars' => $journey['style'] === 'plain' ? null : $journey['stars']['total'],
            'rank' => __('learning::journey.ranks.'.$journey['stars']['rank']['key']),
            'missions' => count(array_filter($journey['missions'], fn (array $m) => $m['state'] !== 'done')),
        ];
    }
}
