<?php

namespace Modules\Platform\Actions\TopUps;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Plans\Requests\PlanRequestAnswered;
use Modules\Platform\Plans\Requests\PlanRequests;

/** The platform team turns down an extra pack an academy asked for, with a reason the academy reads. Audited without it. */
final class DeclineTopUp
{
    public const REASON_MIN = 5;

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(PlatformAdmin $admin, string $topUpId, string $reason): UsageTopUp
    {
        if (! $admin->is_active) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:300']], [], ['reason' => __('platform::plans.attributes.reason')])->validate();

        $topUp = DB::connection('pgsql_platform')->transaction(function () use ($admin, $topUpId, $reason) {
            $topUp = UsageTopUp::query()->lockForUpdate()->findOrFail($topUpId);
            if ($topUp->status !== 'requested') {
                throw ValidationException::withMessages(['topup' => __('platform::topups.errors.answered')]);
            }
            $topUp->update(['status' => 'declined', 'decided_by' => $admin->id, 'decided_at' => now(), 'reason' => $reason]);
            $this->audit->record($topUp->academy_id, 'academy.topup_declined', 'usage_topup', $topUp->id, ['limit' => $topUp->limit, 'amount' => $topUp->amount]);

            return $topUp;
        });

        $academy = Academy::query()->findOrFail($topUp->academy_id);
        PlanRequests::tellRequester($academy->id, $topUp->requested_by, fn (string $locale) => new PlanRequestAnswered('declined', ['reason' => $reason], ActivateTopUp::planUrl($academy), $locale));
        PlanRequests::forget($academy->id);

        return $topUp;
    }
}
