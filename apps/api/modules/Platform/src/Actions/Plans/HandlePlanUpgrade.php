<?php

namespace Modules\Platform\Actions\Plans;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Models\PlatformAdmin;

/**
 * The platform team marks an academy's request for a bigger plan handled,
 * once it has talked to them and changed the plan (ChangeAcademyPlan) or
 * agreed on something else. Audited.
 */
final class HandlePlanUpgrade
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(PlatformAdmin $admin, string $requestId): PlanUpgradeRequest
    {
        if (! $admin->is_active) {
            throw new AuthorizationException;
        }

        return DB::connection('pgsql_platform')->transaction(function () use ($admin, $requestId) {
            $request = PlanUpgradeRequest::query()->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== 'requested') {
                throw ValidationException::withMessages(['plan' => __('platform::topups.errors.answered')]);
            }
            $request->update(['status' => 'handled', 'handled_by' => $admin->id, 'handled_at' => now()]);
            $this->audit->record($request->academy_id, 'academy.upgrade_handled', 'plan_upgrade_request', $request->id);

            return $request;
        });
    }
}
