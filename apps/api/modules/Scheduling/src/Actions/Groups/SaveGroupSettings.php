<?php

namespace Modules\Scheduling\Actions\Groups;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Groups\LateJoin;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The academy's choices for group lessons (docs/architecture/02, "الحصص
 * الجماعية"; Settings › Group lessons): what a student joining a running
 * group gets for the lessons it held (the office may choose otherwise at
 * join time), whether a group may mix ages (and so minors with adults), and
 * the students a new group waits for before it starts (none: its first).
 * Groups already formed keep their own minimum. Audited with the values.
 */
final class SaveGroupSettings
{
    public function handle(string $lateJoin, bool $allowMixedAges, ?int $groupMinDefault): SchedulingSettings
    {
        if (! in_array($lateJoin, LateJoin::CHOICES, true)) {
            throw ValidationException::withMessages(['late_join' => __('scheduling::groups.errors.late_join')]);
        }
        if ($groupMinDefault !== null && ($groupMinDefault < 1 || $groupMinDefault > GroupRules::MAX_SIZE)) {
            throw ValidationException::withMessages(['group_min_default' => __('scheduling::group_screens.settings.errors.min')]);
        }

        return DB::transaction(function () use ($lateJoin, $allowMixedAges, $groupMinDefault) {
            $settings = SchedulingSettings::query()->lockForUpdate()->first() ?? new SchedulingSettings;
            $settings->fill(['late_join' => $lateJoin, 'allow_mixed_ages' => $allowMixedAges, 'group_min_default' => $groupMinDefault])->save();

            app(AuditTrail::class)->record('lesson_group.settings', 'scheduling_settings', null, [
                'late_join' => $lateJoin, 'mixed' => $allowMixedAges ? 'on' : 'off', 'min' => $groupMinDefault ?? '—',
            ]);

            return $settings;
        });
    }
}
