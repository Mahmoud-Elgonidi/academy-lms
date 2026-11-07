<?php

namespace Modules\Platform\Actions\Plans;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Plans\Requests\PlanRequests;

/** The academy takes back its request for a bigger plan while the platform team has not handled it. Audited. */
final class CancelPlanUpgrade
{
    public function __construct(private readonly PlanRequests $requests, private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(string $requestId): PlanUpgradeRequest
    {
        $academy = $this->requests->authorizeAcademy();

        return DB::connection('pgsql_platform')->transaction(function () use ($academy, $requestId) {
            $request = PlanUpgradeRequest::query()->where('academy_id', $academy->id)->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== 'requested') {
                throw ValidationException::withMessages(['plan' => __('platform::topups.errors.answered')]);
            }
            $request->update(['status' => 'cancelled', 'handled_at' => now()]);
            $this->audit->record($academy->id, 'academy.upgrade_cancelled', 'plan_upgrade_request', $request->id);

            return $request;
        });
    }
}
