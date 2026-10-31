<?php

namespace Modules\Quran\Actions\Hifz;

use Illuminate\Support\Facades\Validator;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** The academy chooses how many pages a learner's daily hifz review holds at most (1 to 20). */
final class SaveHifzDailyPages
{
    public const MAX = 20;

    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(int $pages): QuranSettings
    {
        Validator::make(['hifz_daily_pages' => $pages], ['hifz_daily_pages' => ['required', 'integer', 'between:1,'.self::MAX]], [], ['hifz_daily_pages' => __('quran::hifz.settings.daily')])->validate();
        $settings = QuranSettings::current();
        if ($settings->hifz_daily_pages !== $pages) {
            $settings->fill(['hifz_daily_pages' => $pages])->save();
            $this->audit->record('quran.daily_pages_saved', 'quran_settings', $settings->academy_id, ['pages' => $pages]);
        }

        return $settings;
    }
}
