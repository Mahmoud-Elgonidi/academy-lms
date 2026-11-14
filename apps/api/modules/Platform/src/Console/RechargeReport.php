<?php

namespace Modules\Platform\Console;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Models\SaasSubscription;
use Modules\SharedKernel\Plans\Meter;

/**
 * The console's «الاستهلاك والإيرادات» (docs/architecture/02, section 11,
 * "متابعة مالك المنصة"), read from the tables each time on the platform
 * connection: what was sold (units by meter, from the receipts), revenue by
 * pack and currency (never adding currencies together), the paid balance not
 * used yet across academies (a liability) and what of it ends within 30
 * days, the recharges that did not go through, the latest receipts, and per
 * academy its plan, this month's allowance used, bought balance, overage,
 * automatic recharge and last purchase.
 */
final class RechargeReport
{
    public const EXPIRING_DAYS = 30;

    public function __construct(private readonly Meters $meters) {}

    /** @return array<string, array{purchases: int, amount: int}> limit => what was sold since */
    public function sold(?CarbonImmutable $since): array
    {
        return $this->receipts($since)->groupBy('limit')->selectRaw('"limit", count(*) as purchases, sum(amount) as amount')->get()
            ->mapWithKeys(fn ($r) => [$r->limit => ['purchases' => (int) $r->purchases, 'amount' => (int) $r->amount]])->all();
    }

    /** @return list<array{pack_ar: string, pack_en: string, limit: string, currency: string, purchases: int, minor: int}> by currency, then most revenue */
    public function revenue(?CarbonImmutable $since): array
    {
        return $this->receipts($since)->groupBy('pack_name_ar', 'pack_name_en', 'limit', 'currency')
            ->selectRaw('pack_name_ar, pack_name_en, "limit", currency, count(*) as purchases, sum(price_minor) as minor')
            ->orderBy('currency')->orderByDesc('minor')->get()
            ->map(fn ($r) => ['pack_ar' => (string) $r->pack_name_ar, 'pack_en' => (string) $r->pack_name_en, 'limit' => (string) $r->limit,
                'currency' => (string) $r->currency, 'purchases' => (int) $r->purchases, 'minor' => (int) $r->minor])->all();
    }

    /** @return array<string, int> currency => minor units, since */
    public function revenueByCurrency(?CarbonImmutable $since): array
    {
        return $this->receipts($since)->groupBy('currency')->selectRaw('currency, sum(price_minor) as minor')->orderBy('currency')
            ->pluck('minor', 'currency')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, array{left: int, expiring: int}> meter => bought balance still live, and what of it ends soon */
    public function unused(?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $drawn = $this->db()->table('usage_ledger')->whereNotNull('lot_id')->whereColumn('lot_id', '<>', 'id')
            ->groupBy('lot_id')->selectRaw('lot_id, sum(amount) as drawn');
        $rows = $this->db()->table('usage_ledger as l')->leftJoinSub($drawn, 'd', 'd.lot_id', '=', 'l.id')
            ->where('l.kind', Ledger::PURCHASE)->whereColumn('l.lot_id', 'l.id')->where('l.expires_at', '>', $at)
            ->groupBy('l.meter')
            ->selectRaw('l.meter, sum(greatest(l.amount + coalesce(d.drawn, 0), 0)) as left_over, sum(case when l.expires_at <= ? then greatest(l.amount + coalesce(d.drawn, 0), 0) else 0 end) as expiring', [$at->addDays(self::EXPIRING_DAYS)])
            ->get();
        $out = [];
        foreach (Meter::cases() as $meter) {
            $row = $rows->firstWhere('meter', $meter->value);
            $out[$meter->value] = ['left' => (int) ($row->left_over ?? 0), 'expiring' => (int) ($row->expiring ?? 0)];
        }

        return $out;
    }

    /** @return list<TopUpPurchase> the purchases that did not go through, newest first */
    public function failed(?CarbonImmutable $since, int $limit = 30): array
    {
        return TopUpPurchase::query()->whereIn('status', ['failed'])->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->latest()->limit($limit)->get()->all();
    }

    /** @return list<PlatformReceipt> */
    public function latestReceipts(int $limit = 20): array
    {
        return PlatformReceipt::query()->orderByDesc('paid_at')->orderByDesc('seq')->limit($limit)->get()->all();
    }

    /**
     * Each active academy on a plan: this month's allowance used of each meter, its bought balance and overage,
     * automatic recharge, and its last purchase.
     *
     * @return list<array<string, mixed>>
     */
    public function academies(): array
    {
        $subscriptions = SaasSubscription::query()->whereNull('ended_at')->with('plan')->get()->keyBy('academy_id');
        $auto = AutoRecharge::query()->where('enabled', true)->pluck('academy_id')->unique()->flip();
        $last = PlatformReceipt::query()->groupBy('academy_id')->selectRaw('academy_id, max(paid_at) as at')->pluck('at', 'academy_id');
        $out = [];
        foreach (Academy::query()->where('status', 'active')->orderBy('name_en')->get() as $academy) {
            $subscription = $subscriptions[$academy->id] ?? null;
            $meters = [];
            foreach (Meter::cases() as $meter) {
                $state = $subscription === null ? null : $this->meters->state($academy->id, $meter);
                $meters[$meter->value] = $state === null ? null : [
                    'limited' => $state->limited, 'used' => $state->allowanceUsed(), 'allowance' => $state->allowance,
                    'bought' => $state->bought, 'debt' => $state->debt,
                ];
            }
            $out[] = [
                'academy' => $academy,
                'plan' => $subscription?->plan?->name(),
                'meters' => $meters,
                'auto' => isset($auto[$academy->id]),
                'last' => isset($last[$academy->id]) ? CarbonImmutable::parse($last[$academy->id]) : null,
            ];
        }

        return $out;
    }

    private function receipts(?CarbonImmutable $since): Builder
    {
        return PlatformReceipt::query()->when($since, fn ($q) => $q->where('paid_at', '>=', $since));
    }

    private function db(): Connection
    {
        return DB::connection('pgsql_platform');
    }
}
