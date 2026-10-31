<?php

namespace Modules\Learning\Contracts;

/**
 * The Quran on a learner's journey (docs/architecture/02, section 26), which
 * the Quran module binds; the journey is whole without it (NoJourneyQuran).
 */
interface JourneyQuran
{
    /**
     * Today's hifz review for the journey's card: pages left today, done today and a day's pages; null when the
     * learner has none (no hifz map, or the academy's plan leaves the Quran out).
     *
     * @return ?array{due: int, done_today: int, daily: int}
     */
    public function today(string $learnerId): ?array;
}
