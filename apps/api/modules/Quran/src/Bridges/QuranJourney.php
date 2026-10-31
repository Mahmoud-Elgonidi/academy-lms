<?php

namespace Modules\Quran\Bridges;

use Carbon\CarbonImmutable;
use Modules\Learning\Contracts\JourneyQuran;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Queries\Hifz\HifzMap;
use Modules\Quran\Support\QuranArea;

/** Today's hifz review on the learner's journey (Learning's JourneyQuran), once they have a map and while the plan has the Quran. */
final class QuranJourney implements JourneyQuran
{
    public function __construct(
        private readonly HifzMap $map,
        private readonly QuranArea $area,
    ) {}

    public function today(string $learnerId): ?array
    {
        if (! $this->area->on() || ! HifzPage::query()->where('student_id', $learnerId)->exists()) {
            return null;
        }
        $wird = $this->map->wird($learnerId, CarbonImmutable::now());

        return ['due' => count($wird['pages']), 'done_today' => (int) $wird['done_today'], 'daily' => (int) $wird['daily']];
    }
}
