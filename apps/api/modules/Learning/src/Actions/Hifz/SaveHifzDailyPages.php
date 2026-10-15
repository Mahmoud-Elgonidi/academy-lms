<?php

namespace Modules\Learning\Actions\Hifz;

use Illuminate\Support\Facades\Validator;
use Modules\Learning\Models\LearningSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** The academy chooses how many pages a learner's daily hifz review holds at most (1 to 20). */
final class SaveHifzDailyPages
{
    public const MAX = 20;

    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(int $pages): LearningSettings
    {
        Validator::make(['hifz_daily_pages' => $pages], ['hifz_daily_pages' => ['required', 'integer', 'between:1,'.self::MAX]], [], ['hifz_daily_pages' => __('learning::hifz.settings.daily')])->validate();
        $settings = LearningSettings::current();
        if ($settings->hifz_daily_pages !== $pages) {
            $settings->fill(['hifz_daily_pages' => $pages])->save();
            $this->audit->record('learning.hifz_daily_saved', 'learning_settings', $settings->academy_id, ['pages' => $pages]);
        }

        return $settings;
    }
}
