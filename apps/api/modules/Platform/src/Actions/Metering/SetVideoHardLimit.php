<?php

namespace Modules\Platform\Actions\Metering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Platform\Actions\Ai\OwnersOnly;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Metering\AcademyMetering;
use Modules\Platform\Models\PlatformAdmin;

/**
 * The platform's owners choose how an academy's video minutes are held
 * (console › the academy): soft at first (no lesson is ever cut, the balance
 * may go below nothing and the next allowance or purchase covers it), or
 * hard (with nothing left, a new lesson opens on the teacher's outside link
 * instead of our video, and the class page says why). Audited, with the
 * reason kept out.
 */
final class SetVideoHardLimit
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(PlatformAdmin $by, string $academyId, bool $on, string $reason): void
    {
        OwnersOnly::check($by);
        $academy = Academy::query()->findOrFail($academyId);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'min:5', 'max:300']], [], ['reason' => __('platform::plans.attributes.reason')])->validate();

        DB::connection('pgsql_platform')->transaction(function () use ($by, $academy, $on): void {
            $row = AcademyMetering::for($academy->id);
            $row->fill(['video_hard_limit' => $on, 'updated_by' => $by->id])->save();
            $this->audit->record($academy->id, 'academy.video_hard_limit_set', 'academy_metering', $academy->id, ['hard_limit' => $on ? 'on' : 'off']);
        });
    }
}
