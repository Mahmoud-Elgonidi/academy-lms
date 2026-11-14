<?php

namespace Modules\Platform\Actions\TopUps;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Filament\Pages\UsagePage;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\Requests\PlanRequestAnswered;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\Platform\Recharging\Receipts;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Meter;

/**
 * The platform team turns on an extra pack an academy asked for, once paid
 * its own way: it becomes a purchase lot in the academy's usage ledger at
 * once, valid for 12 months and used after the month's allowance (covering
 * any overage first). The request keeps the month it was turned on in. What
 * arrived (the amount the team records; else the pack's price in the
 * academy's currency) makes a paid transfer purchase with its numbered
 * receipt; with neither, no receipt. Audited; whoever asked hears of it.
 */
final class ActivateTopUp
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(PlatformAdmin $admin, string $topUpId, ?Money $received = null): UsageTopUp
    {
        if (! $admin->is_active) {
            throw new AuthorizationException;
        }

        $topUp = DB::connection('pgsql_platform')->transaction(function () use ($admin, $topUpId, $received) {
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
            $lot = app(Meters::class)->addLot($academy->id, Meter::fromLimit($topUp->limitKind()), Ledger::PURCHASE, $topUp->amount, self::expiry(), [
                'ref_type' => 'topup', 'ref_id' => $topUp->id, 'actor_id' => $admin->id,
            ]);
            $money = $received ?? $topUp->pack?->priceIn($academy->base_currency);
            if ($money !== null) {
                $purchase = TopUpPurchase::query()->create([
                    'academy_id' => $academy->id, 'pack_id' => $topUp->pack_id, 'limit' => $topUp->limit, 'amount' => $topUp->amount,
                    'price_minor' => $money->minor, 'currency' => $money->currency, 'method' => TopUpPurchase::TRANSFER, 'status' => 'paid',
                    'requested_by' => $admin->id, 'topup_id' => $topUp->id, 'lot_id' => $lot, 'paid_at' => now(),
                ]);
                app(Receipts::class)->issue($purchase);
            }
            $this->audit->record($academy->id, 'academy.topup_activated', 'usage_topup', $topUp->id, ['limit' => $topUp->limit, 'amount' => $topUp->amount]);

            return $topUp;
        });

        $academy = Academy::query()->findOrFail($topUp->academy_id);
        PlanRequests::tellRequester($academy->id, $topUp->requested_by, fn (string $locale) => new PlanRequestAnswered('active', [
            'extra' => PlanFormat::amountIn($topUp->limitKind(), $topUp->amount, $locale),
            'expires' => self::expiry()->setTimezone($academy->default_timezone)->locale($locale)->translatedFormat('j F Y'),
        ], self::planUrl($academy), $locale));
        PlanRequests::forget($academy->id);

        return $topUp;
    }

    /** The academy's usage page, on its own address. */
    public static function planUrl(Academy $academy): string
    {
        return app(TenantDirectory::class)->baseUrl($academy->toTenantRef()).'/admin/'.UsagePage::getSlug();
    }

    /** Bought balance lasts 12 months from the day it is turned on. */
    public static function expiry(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMonths(Ledger::LOT_MONTHS);
    }
}
