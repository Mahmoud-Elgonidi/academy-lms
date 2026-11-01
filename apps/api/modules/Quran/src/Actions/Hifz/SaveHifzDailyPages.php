<?php

namespace Modules\Quran\Actions\Hifz;

use Modules\Quran\Actions\Settings\SaveQuranSettings;
use Modules\Quran\Models\QuranSettings;

/** The academy chooses how many pages a learner's daily hifz review holds at most (1 to 20), through SaveQuranSettings. */
final class SaveHifzDailyPages
{
    public const MAX = SaveQuranSettings::DAILY_MAX;

    public function __construct(private readonly SaveQuranSettings $settings) {}

    public function handle(int $pages): QuranSettings
    {
        return $this->settings->handle(['hifz_daily_pages' => $pages]);
    }
}
