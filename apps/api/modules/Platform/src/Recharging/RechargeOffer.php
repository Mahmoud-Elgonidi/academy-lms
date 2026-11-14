<?php

namespace Modules\Platform\Recharging;

use Modules\Platform\Metering\MeterFormat;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\PlatformPaymentMethod;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Payments\StripeClient;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Time\Clock;

/**
 * What the academy's usage page shows about recharging, read each time: the
 * packs it can buy online (priced in its currency), its saved card, each
 * meter's automatic recharge, its purchases with their receipts, and where a
 * payment stands once back from Stripe.
 */
final class RechargeOffer
{
    public const PURCHASES = 20;

    public function __construct(private readonly Meters $meters) {}

    /** @return array<string, string> pack id => "Medium · +600 minutes · SAR 50.00", the packs priced in the currency */
    public static function packOptions(Meter $meter, string $currency): array
    {
        return TopUpPack::query()->offered()->where('limit', $meter->limit()->value)->orderBy('sort')->orderBy('amount')->get()
            ->filter(fn (TopUpPack $p) => $p->priceIn($currency) !== null)
            ->mapWithKeys(fn (TopUpPack $p) => [$p->id => $p->name().' · +'.MeterFormat::amount($meter, $p->amount).' · '.$p->priceIn($currency)?->format()])
            ->all();
    }

    /**
     * @param  list<Meter>  $meters
     * @return array<string, mixed>
     */
    public function forAcademy(string $academyId, string $currency, array $meters, ?TopUpPurchase $returned, bool $cancelled): array
    {
        $tz = $this->meters->zone($academyId);
        $locale = app()->getLocale();
        $card = PlatformPaymentMethod::liveFor($academyId);
        $online = StripeClient::configured();

        $auto = [];
        foreach ($meters as $meter) {
            $setting = AutoRecharge::for($academyId, $meter);
            $packs = self::packOptions($meter, $currency);
            $bought = RechargeStatus::boughtThisMonth($academyId, $meter, $this->meters->month($academyId));
            $auto[] = [
                'key' => $meter->value,
                'label' => $meter->label(),
                'state' => match (true) {
                    $setting?->enabled === true => 'on',
                    $setting?->off_reason !== null => 'stopped',
                    default => 'off',
                },
                'text' => $this->autoText($meter, $setting, $currency, $bought),
                'can' => $online && $packs !== [],
            ];
        }

        $purchases = PlatformReceipt::query()->where('academy_id', $academyId)->orderByDesc('paid_at')->orderByDesc('seq')->limit(self::PURCHASES)->get()
            ->map(fn (PlatformReceipt $r) => [
                'id' => $r->id,
                'number' => $r->number,
                'date' => $r->paid_at->setTimezone($tz)->locale($locale)->translatedFormat(Clock::pattern('j M Y · H:i')),
                'pack' => $r->packName($locale),
                'what' => '+'.MeterFormat::amount($r->meter(), $r->amount),
                'method' => __('platform::recharge.methods.'.$r->method),
                'amount' => $r->price()->format($locale),
                'url' => route('platform.receipt', ['receipt' => $r->id]),
            ])->all();

        return [
            'online' => $online,
            'currency' => $currency,
            'card' => $card === null ? null : [
                'brand' => ucfirst($card->brand),
                'last4' => $card->last4,
                'expiry' => $card->expiry(),
                'expired' => $card->expired(),
            ],
            'auto' => $auto,
            'purchases' => $purchases,
            'returned' => $returned === null ? null : $this->returned($returned, $cancelled),
        ];
    }

    private function autoText(Meter $meter, ?AutoRecharge $setting, string $currency, int $bought): string
    {
        $l = 'platform::recharge.auto.';
        if ($setting === null) {
            return __($l.'none');
        }
        if (! $setting->enabled) {
            return $setting->off_reason !== null ? __($l.'stopped', ['why' => __('platform::recharge.off_reasons.'.$setting->off_reason)]) : __($l.'off');
        }
        $pack = $setting->pack;

        return __($l.'on', [
            'threshold' => MeterFormat::amount($meter, $setting->threshold),
            'pack' => $pack === null ? '—' : $pack->name().' (+'.MeterFormat::amount($meter, $pack->amount).($pack->priceIn($currency) ? ' · '.$pack->priceIn($currency)?->format() : '').')',
            'cap' => $setting->monthly_cap,
            'bought' => $bought,
        ]);
    }

    /** @return array{tone: string, title: string, body: string} */
    private function returned(TopUpPurchase $purchase, bool $cancelled): array
    {
        $l = 'platform::recharge.back.';
        $state = match (true) {
            $purchase->status === 'paid' => 'paid',
            $purchase->status === 'open' && $cancelled => 'cancelled',
            $purchase->status === 'open' => 'confirming',
            default => 'not_paid',
        };

        return [
            'tone' => ['paid' => 'success', 'confirming' => 'info', 'cancelled' => 'gray', 'not_paid' => 'warning'][$state],
            'title' => __($l.$state.'.title'),
            'body' => __($l.$state.'.body', ['what' => '+'.MeterFormat::amount($purchase->meter(), $purchase->amount), 'receipt' => (string) $purchase->receipt?->number]),
        ];
    }
}
