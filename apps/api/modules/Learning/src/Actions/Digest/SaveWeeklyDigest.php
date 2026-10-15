<?php

namespace Modules\Learning\Actions\Digest;

use Modules\Learning\Models\LearningSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** The academy turns the weekly note to guardians on or off (off at first). */
final class SaveWeeklyDigest
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(bool $on): LearningSettings
    {
        $settings = LearningSettings::current();
        $settings->fill(['weekly_digest' => $on])->save();
        $this->audit->record('learning.weekly_digest_saved', 'learning_settings', $settings->academy_id, ['state' => $on ? 'on' : 'off']);

        return $settings;
    }
}
