<?php

namespace Modules\Learning\Contracts;

/** No Quran on the journey: the module is not there. */
final class NoJourneyQuran implements JourneyQuran
{
    public function today(string $learnerId): ?array
    {
        return null;
    }
}
