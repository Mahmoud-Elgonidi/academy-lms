<?php

namespace Modules\Scheduling\Filament\Family;

use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Queries\LessonList;
use Modules\SharedKernel\Time\ViewerTimezone;

/** Home of guardians and adults: the next three lessons, with the way in once the classroom opens (a student sees their next lesson on their journey). */
final class NextLessonsWidget extends Widget
{
    protected string $view = 'scheduling::filament.family.next-lessons';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    /** Drawn with the page: no placeholder that jumps, no extra request. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->check() && app(FamilyView::class)->stage((string) auth()->id()) === LearnerStage::Classic;
    }

    /** @return list<array<string, mixed>> */
    public function lessons(): array
    {
        $learner = app(FamilyView::class)->current((string) auth()->id());
        if ($learner === null) {
            return [];
        }
        $now = CarbonImmutable::now();
        $view = new FamilyLessonView($now, app(ViewerTimezone::class)->effective());
        $open = array_filter(app(LessonList::class)->forStudent($learner->id, $now->subHours(2), $now->addWeeks(2)),
            fn (object $r) => in_array($r->status, ['scheduled', 'in_progress'], true) && $r->ends_at->addMinutes(10)->greaterThan($now));

        return array_map($view->lesson(...), array_slice(array_values($open), 0, 3));
    }
}
