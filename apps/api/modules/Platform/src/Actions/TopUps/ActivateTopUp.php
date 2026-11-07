<?php

namespace Modules\Platform\Actions\TopUps;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Filament\Pages\AcademyPlanPage;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\Requests\PlanRequestAnswered;
use Modules\Platform\Plans\Requests\PlanRequests;

/**
 * The platform team turns on an extra pack an academy asked for, once paid
 * its own way: it is added at once to the academy's current calendar month
 * (on its clock; the month it was asked in, if that has passed, is over) and
 * ends with it. Audited; whoever asked hears of it.
 */
final class ActivateTopUp
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(PlatformAdmin $admin, string $topUpId): UsageTopUp
    {
        if (! $admin->is_active) {
            throw new AuthorizationException;
        }

        $topUp = DB::connection('pgsql_platform')->transaction(function () use ($admin, $topUpId) {
            $topUp = UsageTopUp::query()->lockForUpdate()->findOrFail($topUpId);
            if ($topUp->status !== 'requested') {
                throw ValidationException::withMessages(['topup' => __('platform::topups.errors.answered')]);
            }
            $academy = Academy::query()->findOrFail($topUp->academy_id);
            $topUp->update([
                'status' => 'active',
                'period' => PlanRequests::monthKey($academy->default_timezone),
                'decided_by' => $admin->id,
                'decided_at' => now(),
            ]);
            $this->audit->record($academy->id, 'academy.topup_activated', 'usage_topup', $topUp->id, ['limit' => $topUp->limit, 'amount' => $topUp->amount]);

            return $topUp;
        });

        $academy = Academy::query()->findOrFail($topUp->academy_id);
        PlanRequests::tellRequester($academy->id, $topUp->requested_by, fn (string $locale) => new PlanRequestAnswered('active', [
            'extra' => PlanFormat::amountIn($topUp->limitKind(), $topUp->amount, $locale),
            'renews' => PlanRequests::renews($academy->default_timezone, $locale),
        ], self::planUrl($academy), $locale));
        PlanRequests::forget($academy->id);

        return $topUp;
    }

    /** The academy's plan page, on its own address. */
    public static function planUrl(Academy $academy): string
    {
        return app(TenantDirectory::class)->baseUrl($academy->toTenantRef()).'/admin/'.AcademyPlanPage::getSlug();
    }
}
