<?php

namespace Modules\Platform\Actions\Metering;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Metering\AcademyMetering;
use Modules\Platform\Plans\Requests\PlanRequests;

/**
 * The academy chooses whether its staff hear at half of the month's
 * allowance too (Settings › Usage and balance); 80% and 100% are always
 * told. Whoever sees the plan, never while the platform team is signed in as
 * someone. Audited.
 *
 * @throws AuthorizationException|ValidationException
 */
final class SaveUsageAlerts
{
    public function __construct(private readonly PlanRequests $requests, private readonly PlatformAudit $audit) {}

    public function handle(bool $half, string $userId): void
    {
        $academy = $this->requests->authorizeAcademy();
        DB::connection('pgsql_platform')->transaction(function () use ($academy, $half, $userId): void {
            $row = AcademyMetering::for($academy->id);
            if ($row->exists && $row->half_alert === $half) {
                return;
            }
            $row->fill(['half_alert' => $half, 'updated_by' => $userId])->save();
            $this->audit->record($academy->id, 'academy.usage_alerts_saved', 'academy_metering', $academy->id, ['half_alert' => $half ? 'on' : 'off']);
        });
    }
}
