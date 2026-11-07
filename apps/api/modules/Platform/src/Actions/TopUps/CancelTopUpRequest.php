<?php

namespace Modules\Platform\Actions\TopUps;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Plans\Requests\PlanRequests;

/** The academy takes back an extra pack it asked for, while it still waits for the platform team. Audited. */
final class CancelTopUpRequest
{
    public function __construct(private readonly PlanRequests $requests, private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(string $topUpId): UsageTopUp
    {
        $academy = $this->requests->authorizeAcademy();

        $topUp = DB::connection('pgsql_platform')->transaction(function () use ($academy, $topUpId) {
            $topUp = UsageTopUp::query()->where('academy_id', $academy->id)->lockForUpdate()->findOrFail($topUpId);
            if ($topUp->status !== 'requested') {
                throw ValidationException::withMessages(['topup' => __('platform::topups.errors.answered')]);
            }
            $topUp->update(['status' => 'cancelled', 'decided_at' => now()]);
            $this->audit->record($academy->id, 'academy.topup_cancelled', 'usage_topup', $topUp->id, ['limit' => $topUp->limit, 'amount' => $topUp->amount]);

            return $topUp;
        });
        PlanRequests::forget($academy->id);

        return $topUp;
    }
}
